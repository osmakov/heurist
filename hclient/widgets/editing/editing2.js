
/**
* filename: explanation
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

function HEditing(_options) {
     const _className = "Editing",
         _version   = "0.4";

     let $container = null,
         recdata = null,     //HRecordSet with data to be edited
         editing_inputs = [],
         recstructure,
         onChangeCallBack=null,
         entityConfig = null,
         options = {},
         _editStructureMode = false,
         onNewInputCallBack=null;

    /**
    * Initialization
    * options:
    *   container - element Id or jquery element
    *   entity 
    *   recstructure - configuration of fields
    *   recdata - initial data
    *   onchange
    */
    function _init(_options) {
        
        if(typeof tinymce === 'undefined'){
            _loadTinyMCE(function(){
                _init(_options);    
            });
            return;        
        }
        
        if (typeof _options.container==="string") {
            $container = $("#"+_options.container);
        }else{
            $container = $(_options.container);
        }
        if($container==null || $container.length==0){
            $container = null;
            alert('Container element for editing not found');
        }
        
        if(_options.entity){
            entityConfig = _options.entity;
        }else{
            entityConfig = null;
        }
        
        onChangeCallBack = _options.onchange;
        onNewInputCallBack = _options.onrecreate;
        
        if(!_options.className && $container.parents('.editor').length>0) {
                _options.className = '';
        }
        
        options = _options;
        
        _initEditForm(_options.recstructure, _options.recdata);
        
        if(window.hWin.HEURIST4.util.isFunction(_options.oninit)){ //init completed
            _options.oninit.call(that);
        }
    }
    
    //
    //
    //
    function _loadTinyMCE(callback) {
       const tinyMCEPath = window.hWin.HAPI4.baseURL+'external/tinymce5/tinymce.min.js';
       const script = window.hWin.document.createElement('script');
       script.id = 'tiny-mce-script';
       script.onload = function(){  //() => 
         // tinymce is loaded at this point
        
         callback.call(this);
       };
       script.src = tinyMCEPath;
       window.hWin.document.head.appendChild(script);
    }    
    
    //
    // reload existing form        NOT USED
    //
    function _reload(_recdata) {
        
        if(!$container) return;

        recdata = _recdata;

        if(!recdata) return; //nothing to edit

        //create form, fieldset and input elements according to record type/entity structure

        let record = recdata.getFirstRecord();

        if(record!=null){
            //fill form with values
            let idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                let val = recdata.values(record, ele.editing_input('option', 'dtID'));
                if(!Array.isArray(val)) val = [val];
                ele.editing_input('setValue', val );
            }
            
        }
    }
    
    function _setDisabled(mode){
            let idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                ele.editing_input('setDisabled', mode);
            }
    }
    
    function _initEditForm(_recstructure, _recdata, _is_insert){
        
        if(!($container && $container.length>0)) return;
        
        that.wasModified = 0;
        $container.hide();
        $container.empty(); //clear previous edit elements
        editing_inputs = [];
        recdata = _recdata;
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_recstructure) && _recdata==null){
           
            $container.show();
            return;     
        } 
        
        if(_recstructure) recstructure = _recstructure;
                
        let record;
        let recID = '';
        if(window.hWin.HEURIST4.util.isRecordSet(recdata)){
            //for edit mode
                //get record ID
               
                record = recdata.getFirstRecord();
                
                function __findRecID(fields){
                    for (let idx=0; idx<fields.length; idx++){
                       if(fields[idx].groupType){
                           let _recID = __findRecID(fields[idx].children)
                           if(_recID>0) return _recID;
                       }else
                       if(fields[idx]['keyField']){
                           return recdata.fld(record, fields[idx]['dtID']);
                       }
                    }
                    return '';
                }
                recID = __findRecID(recstructure);
        }
        
        
        //rec structure is array in following format
        /*
            only type 'header' can have children
               [
                    {
                    groupHeader: '',
                    groupType: '',  accordeon, tab, group 
                    groupStyle: {}
                    children:[
                        dtID, dtID, dtID, 
                        {groupHeader: , children:},
                    ]
                    },.....
                ],
                
                [
                    {
                    dtID: 'dty_Name',
                    dtFields:{
                        dty_Type:'freetext',
                        rst_DisplayName:'Default field type name:',
                        rst_DisplayHelpText: '', 
                        rst_DisplayExtendedDescription:'',
                        rst_DisplayWidth:60,
                        rst_DefaultValue:'',
                        rst_RequirementType:'required',
                        rst_MaxValues:1
                    }
                    },....]
        */
        
        //special case for group_inside - add it as a child for parent
        
        function __processGroupInside(fields){
        
            let prev_children = null;
            let idx = 0;
            while(idx<fields.length){
                if( $.isPlainObject(fields[idx]) && fields[idx].groupType ){ //this is group
                    
                    if((fields[idx].groupType=='group' || fields[idx].groupType=='accordion_inner' || fields[idx].groupType=='expanded_inner') && prev_children){
                        //move this group inside previous group on the same level
                        prev_children.push(fields[idx]);    
                        fields.splice(idx,1);
                        continue;
                    }else if(fields[idx].groupType=='group_break'){
                        prev_children = null;    
                    }else if (fields[idx].groupType=='group'){ //group inside
                        fields[idx].groupType = 'group_break';  
                    }else if(fields[idx].groupType=='accordion_inner' || fields[idx].groupType=='expanded_inner'){
                        fields[idx].groupType = (fields[idx].groupType=='accordion_inner') ? 'accordion' : 'expanded';
                    }else{
                        prev_children = fields[idx].children;    
                    }
                        
                        //__processGroupInside(fields[idx].children);  
                    
                }
                idx++;
            }//for
        
        }//__processGroupInside
        __processGroupInside(recstructure);
        
        function __createGroup(fields, groupContainer, fieldContainer){
            let idx, tab_index;
                
            let currGroupType = null, currGroupHeaderClass = null; //current accodion or tab control
            let groupTabHeader, groupEle;
            let hasVisibleFields = false;
            
            //    groupEle,      //current accordion or tab control
           
                
            for (idx=0; idx<fields.length; idx++){
                
                if( $.isPlainObject(fields[idx]) && fields[idx].groupType ){ //this is group
                //  window.hWin.HEURIST4.util.isArrayNotEmpty(fields[idx].children)
                    
                    if(fields[idx].groupHidden || fields[idx]['groupTitleVisible']===false){ //this group is hidden all fields goes to previous group

                        if(fieldContainer == null){
                            fieldContainer = groupContainer.find('fieldset').last();
                        }

                        __createGroup(fields[idx].children, groupContainer, fieldContainer);
                        continue;                        
                    }else if(fields[idx].groupType=='group'){ //group inside

                        let headerText = fields[idx]['groupHeader'];
                        let headerHelpText = fields[idx]['groupHelpText'];
                        const is_header_visible = fields[idx]['groupTitleVisible'];

                        if(headerText == '-'){ // Placeholder for no text, just use a simple divider
                            headerText = '';
                            headerHelpText = '';
                        }else if(headerHelpText == 'new separator'){ // remove default separator help
                            headerHelpText = '';
                        }

                        let hele = $('<h4>')
                            .text(headerText).addClass('separator').appendTo(fieldContainer);
                        
                        hele.css({'margin-bottom':'4px'});
                        
                        let div_prompt = $('<div>').text(headerHelpText)
                               .addClass('heurist-helper1')
                               .addClass('separator-helper').css({'padding-left':'20px','padding-bottom':'4px'})
                               .appendTo(fieldContainer);
                        if(!is_header_visible){
                            hele.addClass('separator-hidden').hide();
                            div_prompt.addClass('separator-hidden').hide();
                        }

                        //container for gear-wheel                              
                        let ele = $('<div>').appendTo(fieldContainer);    
                        if(parseInt(fields[idx]['dtID'])>0){ //for Records only
                            ele.attr('data-dtid', fields[idx]['dtID']);
                        }
                        
                        __createGroup(fields[idx].children, groupContainer, fieldContainer);
                        continue;
                    }else if(fields[idx].groupType=='accordion_inner' || fields[idx].groupType=='expanded_inner'){ // accordion within another group

                        let headerText = fields[idx]['groupHeader'];
                        let headerHelpText = fields[idx]['groupHelpText'];

                        let $group_ele = $('<div>').css('width', '100%').appendTo(fieldContainer);
                        let $field_ele = $('<fieldset>').addClass(options.className).appendTo($group_ele);

                        let $help_ele = $('<div>').text(headerHelpText)
                            .addClass('heurist-helper1 tab-separator-helper')
                            .css({padding:'5px 0 0 5px',display:'inline-block'})
                            .appendTo($field_ele);

                        $('<h3>').html('<span class="separator2">'+headerText+'</span>').appendTo($group_ele);
                        $field_ele.appendTo($('<div>').css('border', 'none').appendTo($group_ele));

                        if(parseInt(fields[idx]['dtID'])>0){
                            $field_ele.attr('data-dtid', fields[idx]['dtID']);
                            $help_ele.attr('separator-dtid', fields[idx]['dtID']);
                        }

                        $group_ele.accordion({
                            heightStyle: 'content',
                            active: (fields[idx].groupType == 'expanded_inner') ? 0 : false,
                            collapsible: true
                        }).css('width', '100%');


                        __createGroup(fields[idx].children, groupContainer, $field_ele);
                        continue;
                    }
                    
                    if(fields[idx].groupType != currGroupType){ //create new group container and init previous
                    
                    
                        //init previous one 
                        if(groupEle!=null){
                            if(currGroupType == 'accordion' || currGroupType == 'expanded'){
                                groupEle.accordion({heightStyle: "content", 
                                                    active:(currGroupType == 'expanded')?0:false, 
                                                    collapsible: true});
                                if(currGroupHeaderClass){
                                    groupEle.find('.ui-accordion-header').addClass(currGroupHeaderClass);
                                }
                            }else if(currGroupType == 'tabs'){
                                groupEle.tabs().addClass('edit-form-tabs');
                            }
                        }
                
                        currGroupHeaderClass= fields[idx].groupHeaderClass;
                        currGroupType = (fields[idx].groupType == 'tabs_new')
                                                  ?'tabs':fields[idx].groupType;
                        //create new accordion or tabcontrol
                        if(currGroupType == 'accordion' || currGroupType == 'expanded'){
                            groupEle = $('<div>').appendTo(groupContainer);
                        }else if(currGroupType == 'tabs'){
                            //header(tabs)
                            groupEle = $('<div>').appendTo(groupContainer);
                            groupTabHeader = $('<ul>').appendTo(groupEle);
                            
                        }else{
                            groupEle = null;
                        }
                        if(groupEle && fields[idx].dtID>0){
                                groupEle.attr('data-group-dtid', fields[idx].dtID);
                        }
                        
                        tab_index = 0;
                    }
                    
                    let headerText = fields[idx]['groupHeader'];
                    let headerHelpText = fields[idx]['groupHelpText'];
                    const is_header_visible = fields[idx]['groupTitleVisible'];
                    
                    let newFieldContainer = $('<fieldset>').uniqueId();
                    if(!$.isEmptyObject(fields[idx]['groupStyle'])){
                        newFieldContainer.css(fields[idx]['groupStyle']);    
                    }
                    if(parseInt(fields[idx]['dtID'])>0){ //for Records only
                    
                        newFieldContainer.attr('data-dtid', fields[idx]['dtID']);

                        if(!(currGroupType == 'tabs' || currGroupType == 'accordion' || currGroupType == 'expanded')){
                            //div for gearwheel
                            $('<div>').css({'padding-left':'7px','height':'12px','display':'inline-block'}) 
                                .attr('data-dtid', fields[idx]['dtID'])
                                .appendTo(groupContainer);
                        }else{
                            newFieldContainer.attr('data-tabindex', tab_index);
                            tab_index++;
                        }
                    }

                    //add header and field container
                    if(currGroupType == 'accordion' || currGroupType == 'expanded'){
                        $('<h3>').html('<span class="separator2">'+headerText+'</span>').appendTo(groupEle);
                        newFieldContainer.appendTo($('<div>').appendTo(groupEle));

                        newFieldContainer.addClass(options.className);

                    }
                    else if(currGroupType == 'tabs'){
                        // class="separator2"
                        $('<li>').addClass('edit-form-tab').html('<a href="#'+newFieldContainer.attr('id')+'"><span style="font-weight:bold">'+headerText+'</span></a>')
                        .appendTo(groupTabHeader);

                        $(newFieldContainer).appendTo(groupEle);

                        newFieldContainer.addClass(options.className);

                    }
                    else{
                        
                        let ele = $('<h4>').text(headerText).addClass('separator');
                        
                        ele.appendTo(groupContainer);    

                        if(!is_header_visible){
                            ele.addClass('separator-hidden').hide();
                        }

                        newFieldContainer.appendTo(groupContainer);
                    }
                    const is_show_header_help_text = true;
                    if(is_show_header_help_text){ // || headerHelpText!=''
                         let div_prompt = $('<div>').text(headerHelpText)
                            .addClass('heurist-helper1')
                            .appendTo(newFieldContainer);
                         if(currGroupType == 'tabs' || currGroupType == 'accordion'){
                            div_prompt.addClass('tab-separator-helper')
                                .attr('separator-dtid',fields[idx]['dtID']).css({padding:'5px 0 0 5px',display:'inline-block'});
                         }else{
                            div_prompt.addClass('separator-helper').css({'padding-left':'14px'});
                         }
                         if(!is_header_visible){
                            div_prompt.addClass('separator-hidden').hide();
                         }
                    }
                    if(currGroupType == 'tabs'){ //some space on the top
                        $(newFieldContainer).append('<div style="min-height:10px">&nbsp;</div>');
                    }
                        
                    __createGroup(fields[idx].children, groupContainer, newFieldContainer);
                    
                    //reset fieldContainer
                    fieldContainer = null;
                    
                }//has children
                else{ //this is entry field 
                
                    if(fieldContainer==null){ 
                        //we do not create it before loop to avoid create empty fieldset 
                        // in case first element is group
                        fieldContainer = $('<fieldset>').uniqueId().appendTo(groupContainer);
                    }
                    
                    if(fields[idx]['dty_Type']=="separator"){
                        $('<h4>').text(fields[idx]['rst_DisplayName']).addClass('separator').appendTo(fieldContainer);
                        $('<div>')
                            .text(top.HEURIST4.ui.getRidGarbageHelp(fields[idx]['rst_DisplayHelpText']))
                            .addClass('heurist-helper1').appendTo(fieldContainer);
                        //see applyCompetencyLevel
                    }else  
                    {
                        
                        //assign values from record
                        if(record!=null){
                            
                            let val;
                            if(fields[idx]['dty_Type']=="geo"){
                                val = recdata.getFieldGeoValue(record, fields[idx]['dtID']);
                            }else{
                                val = recdata.values(record, fields[idx]['dtID']);
                            }

                            if(!window.hWin.HEURIST4.util.isnull(val)){
                                if(!Array.isArray(val)) val = [val];
                                fields[idx].values = val;
                            }else{
                                fields[idx].values = null;
                            }  
                            
                        }else{
                        //new record - reset all values    
                            fields[idx].values = null;    
                        }
                        
                        fields[idx].recID = recID;
                        fields[idx].recordset = recdata;
                        fields[idx].editing = that;
                        fields[idx].change = _onChange;
                        fields[idx].onrecreate = onNewInputCallBack;
                        fields[idx].is_insert_mode = _is_insert;
                        
                        
                        let inpt = $('<div>').css('display','block !important')
                                .appendTo(fieldContainer).editing_input(fields[idx]);     
                        //mark each field with dty_ID         
                        if(parseInt(fields[idx]['dtID'])>0){ //for Records only
                            inpt.attr('data-dtid', fields[idx]['dtID']);
                        }
           
                        editing_inputs.push(inpt);  
                        
                        hasVisibleFields = true;
                    }
                }//end field addition
                
            }//for
            
            //init last one
            if(groupEle!=null){
                if(currGroupType == 'accordion' || currGroupType == 'expanded'){
                    groupEle.accordion({heightStyle: "content", 
                                        active:(currGroupType == 'expanded')?0:false,
                                        collapsible: true });
                    if(currGroupHeaderClass){
                        groupEle.find('.ui-accordion-header').addClass(currGroupHeaderClass);
                    }
                }else if(currGroupType == 'tabs'){
                    groupEle.tabs({active: 0}).addClass('edit-form-tabs');;
                }
            }
            
            if(!hasVisibleFields && fieldContainer.find('.input-cell').length == 0){
                $('<div>There are no fields visible under this heading/tab. Please define new fields or move fields into this section.</div>')
                    .addClass('heurist-helper3').appendTo(fieldContainer);
            }
        }//end of function

        $container.addClass(options.className);


        if(entityConfig && entityConfig.entityDescription){
            //add description at the beginning of form
            $('<div>').css({padding: '4px'}).addClass('heurist-helper2 entity-description')
                .html(entityConfig.entityDescription).appendTo($container);
        }
        
        __createGroup(recstructure, $container, null);
        
       
        $container.fadeIn(250);
        
        _setFocus();
        adjustHelpWidth();
        
        let $div_hints = $('<div>').css({'padding-top':'5px', 'padding-left': '180px'}).appendTo($container); //float: 'left'
        if($container.find('.forbidden').length>0 && window.hWin.HAPI4.is_admin()){
            $('<div>').css({padding: '4px'})
                .addClass('hidden_field_warning')
                .html('There are hidden fields in this form. <span class="btn-modify_structure"'
                +'  style="cursor:pointer;display:inline-block;color:#7D9AAA;">'
                +'Modify structure</span> to enable them.').appendTo($div_hints);
        }

        let is_Records = recdata && recdata.entityName=='Records';
        if(is_Records && $container.find('ul[role="tablist"]').length>0){
            
            let tab_groups = $container.find('ul[role="tablist"]');
            $.each(tab_groups, function(idx, group){
                let $tabs = $(group).find('a');
                let max_char = 30;

                $tabs.attr('style', 'max-width:'+max_char+'ex;width:auto;padding-right:30px !important;cursor:pointer;').addClass('truncate');
            });
			
			if($container.find('ul[role="tablist"]').length>1){
                let groups = $container.children('div');

                $.each(groups, function(idx, group){
                    if(idx == 0){
                        return;
                    }

                    if($(group).hasClass('ui-tabs') && $(groups[idx-1]).is('.ui-tabs, .ui-accordion')){
                        $(group).css('margin-top', $(groups[idx-1]).hasClass('ui-tabs') ? '40px' : '20px');
                    }
                });
            }
        }
        if(is_Records && $container.find("div.optional").length > 0){
            $('<div>').css({padding: '4px'}).addClass('optional_hint')
                    .html('Fields missing? Turn on <u>optional fields</u> (checkbox at the top of page)').appendTo($container);
        }
    }
    
    //
    //
    //
    function _setFocus(){
        if(editing_inputs.length>0){
            let idx, ele;
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                if(ele.editing_input('instance') && ele.editing_input('focus')){
                    break;    
                }
            }
        }
    }
    
    //
    //to reduce width of edit form set heurist-helper1 to max width of input element
    //
    function adjustHelpWidth(){
        
        let maxW = 300;
        if(editing_inputs.length>0){
            let idx, ele; 
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                maxW = Math.max(maxW, ele.editing_input('getInputWidth'));
            }
        }
        
        //for all except image selector help
        $container.find('.input-cell').each(function(i,item){
               if(!$(item).find('.image_input')) {
                   $(item).find('.heurist-helper1').width(maxW);
               }
        });
        
       
    }
    

    //
    //
    //
    function _save(){
        alert('save');
        return true;
    }

    //
    // returns array with at least one empty value
    //
    function _getValue(dtID){
        let idx, ele, values = [];
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.editing_input('instance') && ele.editing_input('option', 'dtID')==dtID){
                let vals = ele.editing_input('getValues');
                if(vals && vals.length>0){
                    return ele.editing_input('getValues');
                }else{
                    return null;
                }
            }
        }
        return null;
    }
    
    //
    // returns values as object {'xyz_Field':'value','xyz_Field2':['val1','val2','val3]}
    //    
    function _getValues(needArrays){
        
        let details = {};
        for (let idx in editing_inputs) {
            let ele = $(editing_inputs[idx]);
            let vals = ele.editing_input('getValues');

            if(vals && vals.length>0){
                
                let a_val;
                if(needArrays || vals.length>1 || $.isPlainObject(vals[0])){
                    a_val = vals;
                }else{
                    a_val = vals[0];   
                }
                details[ ele.editing_input('option', 'dtID') ] = a_val;
            }
        }
        return details;
    }
    
    //
    // get field visibilities from edit form
    // returns object {dty_ID:[1,1,0,0,1],.....  }
    // 
    function _getFieldsVisibility(){
        
        let idx, ele, details = {};
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            let dty_ID = ele.editing_input('option', 'dtID'); //field type id
            
            if(window.hWin.HEURIST4.util.isNumber(dty_ID) && dty_ID>0){
                let vals = ele.editing_input('getVisibilities');
                if(vals && vals.length>0){
                    details[ dty_ID ] = vals;
                }
            }
        }
        return details;
    }

    //
    // set current visibility setting in edit form from record
    // 
    function _setFieldsVisibility( recdata ){

        if(recdata!=null){ //for edit mode
            let record = recdata.getFirstRecord();
        
            for (let idx in editing_inputs) {
                let ele = $(editing_inputs[idx]);
                
                let dty_ID = ele.editing_input('option', 'dtID'); //field type id
                
                if(window.hWin.HEURIST4.util.isNumber(dty_ID) && dty_ID>0){
                    let visibilities = recdata.getFieldVisibilites(record, dty_ID); //from record['v']
                    ele.editing_input('setVisibilities', visibilities);
                }
            }
        }
    }
    
    //
    // get values from editing form and assign to underlaying recordset
    //
    function _assignValuesIntoRecord(){
    
        if(recdata!=null){ //for edit mode
            let record = recdata.getFirstRecord();
            
            for (let idx in editing_inputs) {
                let ele = $(editing_inputs[idx]);
                let vals = ele.editing_input('getValues');
                if(vals && vals.length>0){
                    recdata.setFld(record, ele.editing_input('option', 'dtID'), vals);
                }
            }
        }
    }

    
    function _isModified(){
        
        if(that.wasModified==2){ //modfied flag is reset (after save)
            return false;
        }else if(that.wasModified==1){
            return true;
        }else{
            for (let idx in editing_inputs) {
                let ele = $(editing_inputs[idx]);
                
                if(ele.editing_input('instance') && ele.editing_input('isChanged')) {
                    return true;   
                }
            }
            return false;
        }
    }
    
    function _onChange(){
        if(window.hWin.HEURIST4.util.isFunction(onChangeCallBack)){
            onChangeCallBack.call( this );    
        }
    }
    
    function _validate(){
        
        let idx, res = true;
        for (idx in editing_inputs) {
            res = $(editing_inputs[idx]).editing_input('validate') && res;
        }
        
        return res;
    }
    
    //
    // returns input element for given field
    // 
    function _getFieldByName(fieldName){
        let idx, ele;
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.editing_input('instance') && ele.editing_input('option', 'dtID')  == fieldName){
                return ele;
            }
        }
        return null;
    }

    //
    // returns array of input elements for field value
    // 
    function _getFieldByValue(fieldName, value){
        let idx, ele, ress = [], val;
        if(value==='[not empty]'){
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                val = ele.editing_input('f', fieldName)
                if(!window.hWin.HEURIST4.util.isempty(val)){
                    ress.push(ele);
                }
            }
        }else{
            for (idx in editing_inputs) {
                ele = $(editing_inputs[idx]);
                if(ele.editing_input('f', fieldName)  == value){
                    ress.push(ele);
                }
            }
        }
        
        return ress;
    }

    //
    // returns array of input elements for field value
    // 
    function _getFieldByClass(className){
        let idx, ele, ress = [];
        for (idx in editing_inputs) {
            ele = $(editing_inputs[idx]);
            if(ele.hasClass(className)){
                ress.push(ele);
            }
        }
        return ress;
    }

        
    //
    // returns array of input elements for given field
    // fieldName is rec_XXX or dty_ID
    // 
    function _getInputs(fieldName){
        let ele = _getFieldByName(fieldName);
        if(ele && ele.length>0){
            return ele.editing_input('getInputs');
        }
    }

    //
    // Add extra field value errors
    //
    function _displayValueErrors(fieldNames){

        if(!Array.isArray(fieldNames)){
            fieldNames = fieldNames.split(',');
        }

        for(const fieldName of fieldNames){

            let ele = _getFieldByName(fieldName);
            let record = recdata.getFirstRecord();

            if(ele && ele.length > 0 && record?.errors?.[fieldName]){
                ele.editing_input('showValueErrors', record.errors[fieldName]);
            }
        }
    }


    //public members
    let that = {
        
        wasModified: 0, //0 not modified (on init), 1 was modified, 2 - finalized(not modified)

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        //assign values only based on old structure
        reload: function(_recdata){
            _reload(_recdata);
        },

        validate: function(){
            return _validate();
        },

        save: function(){
            _save();
        },
        
        //
        // create edit form and fill values from given recordset
        //
        initEditForm: function(_recstructure, _recdata, _is_insert){
            _initEditForm(_recstructure, _recdata, _is_insert);
        },
        
        // returns array with at least one empty value
        //
        getValue:function(dtID){
            return _getValue(dtID);
        },
        
        //
        // returns all values of editing form as json
        //
        getValues:function(needArrays){
            return _getValues(needArrays);
        },
        
        //
        // gets individual visibility setting per field 
        // 
        getFieldsVisibility: function(){
            return _getFieldsVisibility();
        },
        
        //
        // sets individual visibility setting per field 
        //        
        setFieldsVisibility: function(vals) {
            _setFieldsVisibility( vals );    
        },
        
        //
        // get values from editing form and assign to underlaying recordset
        //
        assignValuesIntoRecord:function(){
            _assignValuesIntoRecord();
        },
        
        //
        // returns editing_element by name
        //
        getFieldByName:function(fieldName){
            return _getFieldByName(fieldName);
        },

        // 
        // is_changed == false do not set flag as modified
        // editing element will be recreated!
        //
        setFieldValueByName:function(fieldName, value, is_changed){
            let ele = _getFieldByName(fieldName);
            if(ele && ele.editing_input('instance')){
                ele.editing_input('setValue', Array.isArray(value)?value:[value], (is_changed===false));
                if(is_changed!==false){
                    ele.editing_input('isChanged', true);    
                    ele.editing_input('onChange');
                   
                }
            }
        },
        
        //
        // is_changed == false do not set flag as modified
        // editing element will NOT be recreated!
        // value assigned to the first input element only
        //
        setFieldValueByName2:function(fieldName, value, is_changed){
        
            let ele = _getFieldByName(fieldName);
            if(ele && ele.editing_input('instance')){
                
                    let elements = ele.editing_input('getInputs') //_getInputs(fieldName);               
                    $(elements[0]).val( value );
                            
                    if(is_changed!==false){
                        ele.editing_input('isChanged', true);    
                        ele.editing_input('onChange');
                    }
            }
        },
        
        //
        //
        //
        getAllFields: function(){
            return editing_inputs;    
        },
        
        //
        // returns array of ALL input elements by field name
        //
        getInputs:function(fieldName){
            return _getInputs(fieldName);
        },
        
        //
        // returns array of input elements by value
        //
        getFieldByValue:function(fieldName, value){
            return _getFieldByValue(fieldName, value);
        },
        
        getFieldByClass:function(className){
            return _getFieldByClass(className);
        },
        
        isModified: function(){
            return _isModified();
        },
        
        setModified: function(val){
            
            if(val===0){
                that.wasModified = 0;    
                for (let idx in editing_inputs) {
                    let ele = $(editing_inputs[idx]);
                    
                    if(ele.editing_input('instance')){
                        ele.editing_input('setUnchanged');
                    }
                }
                
            }else{
                that.wasModified = (val===false)?2:1;
            }
        },
        
        
        
        getContainer: function(){
            return $container;
        },
        
        setDisabled: function(mode){
            _setDisabled(mode);
        },
        
        setFocus: function(){
            _setFocus();
        },
        
        editStructureFlag: function(value){
            if(value===true || value===false){
                _editStructureMode = value;      
            }else{
                return _editStructureMode;
            }
        },
        
        getOptions: function (){
            return options;
        },

        displayValueErrors: function(fieldNames){
            _displayValueErrors(fieldNames);
        }
    }

    _init(_options);
    return that;  //returns object
}