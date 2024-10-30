/**
* searchBuilderItem.js - element in filter builder - to define query element
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @designer    Ian Johnson     <ian.johnson.heurist@gmail.com>
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

$.widget( "heurist.searchBuilderItem", {

    //{ conjunction: [ {predicate} , {predicate}, .... ] }
    //
    // predicate    token:value
    //
/*    
     ids, id: record ID 
     title: record title 
     url, u: record url 
     notes, n: record notes (`rec_ScratchPad`) 
     added: creation date 
     date, modified:  edition date 
     after, since, before: aliases for added:&gtdate and added&lt;date 

                                  
     addedby: added by specified user (`rec_AddedByUGrpID`) 
     owner,workgroup,wg: owner of record (`rec_OwnerUGrpID`) 
      
     tag, keyword, kwd: tag name (`usrtags`.`tag_Text`) 
     user, usr: bookmarked by user (`usrbookmarks`.`bkm_UGrpID`) 

      
     t,type: record type 
     f,field: field type id 
     r:  reltype value
     linked_to,linkedfrom,related_to,relatedfrom,links: various link predicates 
*/    
    
    // default options
    options: {
        //token:null,  //t, f, linkedXXX
        
        code: null, //hierarchy

        top_rty_ID: 0,
        rty_ID: 0,
        
        dty_ID: 0, //field id or token   
        
        enum_field: null, //subfield for enums    
        
        hasFieldSelector: false,
        
        // callback
        onremove: null,
        onchange: null,
        
        onselect_field: null,  //callback to select field from treeview

        language: null, // selected language (3 character ISO639-2 code)
        
        reverse_RtyID: 0  // rectord type for reverse links
    },

    _current_field_type:null, // type of input field
    _predicate_input_ele:null,     // reference to editing_input
    _predicate_reltype_ele:null,     // reference to relation type selector

    _all_fields: null, // field cache for any record type

    // the widget's constructor
    _create: function() {

        let that = this;
        
        //create elements for predicate
        // 2. field selector for field or links tokens
        // 3. comparison selector or relationtype selector
        // 4. value input
        // 5. OR button
        
        
        // 0. Label (header)
        this.label_token = $( "<div>" )
            .css({"font-size":"smaller",'padding-left':'94px',width:'95%','margin-top':'4px'})
            .appendTo( this.element ); //10px 0 10px 20px,'border-top':'1px solid lightgray' 

        // selector container - for fields and comparison
        this.sel_container = $('<div>')
            .css({'display':'inline-block','vertical-align':'top','padding-top':'3px'})
            .appendTo(this.element);

        // values container - consists of set of inputs (editing_input) and add/remove buttons
        this.values_container = $( '<fieldset>' )
            .css({'display':'inline-block','padding':0}) //,'margin-bottom': '2px'
            .appendTo( this.element );

            
        $('<div class="header_narrow field_header" '
        +'style="min-width:90px;display:inline-block;text-align:right;padding-right: 9px;">'
        +'<label for="opt_rectypes">Criteria</label></div>')
            .appendTo( this.sel_container );
        
        // 2. field selector for field or links tokens
        this.select_fields = $( '<select>' )
            .attr('title', 'Select field' )
            .addClass('text ui-corner-all')
            .css({'margin-left':'2em','min-width':'210px','max-width':'210px'})
            .hide()
            .appendTo( this.sel_container );

        this.select_fields_btn = $('<span role="combobox" class="ui-selectmenu-button ui-button '
                    +'ui-widget ui-selectmenu-button-closed ui-corner-all" '
                    +'style="padding: 0px; font-size: 1.1em; width: 210px; min-width: 210px;">'
                    +'<span class="ui-selectmenu-icon ui-icon ui-icon-triangle-1-s"></span><span class="ui-selectmenu-text">Any field</span></span>')
                .insertAfter(this.select_fields);
        this._on( this.select_fields_btn, { click: function(event){
            if(window.hWin.HEURIST4.util.isFunction(this.options.onselect_field)){
                window.hWin.HEURIST4.util.stopEvent(event);
                this.options.onselect_field.call(this);
               
            }
        }});


        // 1. Remove icon
        this.remove_token = $( "<span>" )
        .attr('title', 'Remove this search token' )
        .addClass('ui-icon ui-icon-circle-b-close')
        .css({'cursor':'pointer','font-size':'0.8em',visibility:'hidden'})
        .appendTo( this.sel_container );        
        
        this._on( this.remove_token, { click: function(){
            if(window.hWin.HEURIST4.util.isFunction(this.options.onremove)){
                this.options.onremove.call(this);
            }    
        } });
            
        // 3a  negate  
        this.cb_negate = $( '<label><input type="checkbox">not</label>' )
            .css('font-size','0.8em')
            .hide()
            .appendTo( this.sel_container );

        
        // 3b. comparison selector or relationtype selector
        this.select_comparison = $( '<select>' )
            .attr('title', 'Select compare operator' )
            .addClass('text ui-corner-all')
            .css({'margin-left':'1em','min-width':'130px','max-width':'130px',border:'none'})
            //.hide()
            .appendTo( this.sel_container );

            
        // 4. conjunction selector for multivalues
        this.select_conjunction = $( '<select><option value="any">or</option><option value="all">and</option></select>' )
            .attr('title', 'Should field satisfy all criteria or any of them' )
            .addClass('text ui-corner-all')
            .css({'margin':'10px 0px 2px 8px',border:'none',width:33}) //mr:1 w:40 for "and"
            .appendTo( this.sel_container );
            
        
        this.select_relationtype = $( '<select>' )
            .attr('title', 'Select relation type' )
            .addClass('text ui-corner-all')
            .css('margin-left','2em')
            .hide()
            .appendTo( this.sel_container );
        

        this.sel_container
        .on( 'mouseenter', function(event){ 
            that.remove_token.css({visibility:'visible'});
        } )
        .on( 'mouseleave', function(event){
                   that.remove_token.css({visibility:'hidden'});
        } );
        
        this._refresh();
        
        
        if(!this.options.hasFieldSelector){
            this._defineInputElement();            
        }
        
        
        
    }, //end _create
    
    //
    //
    //
    changeOptions: function(ext_options){

        if(ext_options.enum_field == 'term'){ // for term labels, default comparison to equals //ext_options.code != this.options.code && 
            this.select_comparison.val('=');
        }

        this.options = $.extend(this.options, ext_options);
        
        this._refresh();
    },
    
    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        if(!this.options.hasFieldSelector){
            this.remove_token.css({'margin-top':'-55px'});
            //this.label_token.show();    
        }else{
            //this.label_token.hide();    

           
            let topOptions2 = [
                {key:0,title:'Generic fields', group:1, disabled:true},
                {key:'title',title:'Title (constructed)', depth:1},
                {key:'added',title:'Date added', depth:1},
                {key:'modified',title:'Date modified', depth:1},
                {key:'addedby',title:'Creator (user)', depth:1},
                {key:'url',title:'URL', depth:1},
                {key:'notes',title:'Notes', depth:1},
                {key:'owner',title:'Owner (user or group)', depth:1},
                {key:'access',title:'Visibility', depth:1},
                {key:'tag',title:'Tags', depth:1},
                {key:'anyfield',title:window.hWin.HR('Any field')}
            ];

            let bottomOptions = null;
            //[{key:'latitude',title:window.hWin.HR('geo: Latitude')},
            //                     {key:'longitude',title:window.hWin.HR('geo: Longitude')}]; 

            if(this.options.top_rty_ID>0){
                
                this.select_fields_btn.show();
                this.select_fields.hide();

            }else{
                let allowed_fieldtypes = ['enum','freetext','blocktext',
                    'geo','year','date','integer','float','resource','relmarker'];

                this.select_fields_btn.hide();
                this.select_fields.show();

                if(!this._all_fields){
                    this._all_fields = $Db.getBaseFieldInstances(null, 1, allowed_fieldtypes, []);
                }
                window.hWin.HEURIST4.ui.createSelector(this.select_fields[0], [...topOptions2, ...this._all_fields]);

                this.select_fields.val( this.options.dty_ID ? this.options.dty_ID : 'anyfield' );

                window.hWin.HEURIST4.ui.initHSelect(this.select_fields[0], false);

                this._on( this.select_fields, { change: this._onSelectField });
            }
            this._onSelectField();

        }

    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

    },

    _defineInputElement: function( field_type ){

        if(this.options.code){
            let res = $Db.parseHierarchyCode(this.options.code, this.options.top_rty_ID);
            if(res!==false){
                if(this.options.top_rty_ID>0){
                    
                    let lbl_text = res.harchy[res.harchy.length-1];
                    if(this.options.enum_field!=null){
                        lbl_text = lbl_text + '.'+this.options.enum_field;
                    }
                    
                    this.element
                        .find('span.ui-selectmenu-button>span.ui-selectmenu-text')
                        .text(lbl_text);

                }

                if(res.harchy.length>2){
                    res.harchy.pop();
                    this.label_token.html(res.harchy.join(''));
                    this.label_token.show();
                }else{
                    this.label_token.hide();
                }
                
            }else if(this.options.reverse_RtyID>0){
                let lbl_text = '< '+$Db.rty(this.options.reverse_RtyID, 'rty_Name');
                    this.element
                        .find('span.ui-selectmenu-button>span.ui-selectmenu-text')
                        .text(lbl_text);
                
            }else{
                this.label_token.text('broken!');
            }
        }else if(this.options.dty_ID>0){
            let lbl_text = $Db.dty(this.options.dty_ID,'dty_Name');
            if(this.options.enum_field!=null){
                lbl_text = lbl_text + '.' + this.options.enum_field;
            }
            this.label_token.text(lbl_text);    
        }
        
        let that = this;


        let ed_options = {
            recID: -1,
            //dtID: dtID,
            values: [''],
            readonly: false,
            showclear_button: true,
            show_header: false,
            showedit_button: false,
            suppress_prompts: true,  //supress help, error and required features
            suppress_repeat: 'force_repeat',
            dtFields: null,
            is_faceted_search: true,
            
            change: function(){
                that._manageConjunction();
                
                if(window.hWin.HEURIST4.util.isFunction(that.options.onchange))
                {
                    that.options.onchange.call(this);
                }
        
            },
            onrecreate:function(){
                that._manageConjunction();
            }
            
        };

        let dty_ID = this.options.dty_ID;

        if(dty_ID.indexOf('r.')==0){
            dty_ID = dty_ID.substr(2);    
        }else if(dty_ID.indexOf('lt')==0 || dty_ID.indexOf('lf')==0){
            dty_ID = dty_ID.substr(2);    
        }
        
        if(dty_ID>0){ //numeric - base field

            let compare = this.select_comparison.val();

            field_type = $Db.dty(dty_ID,'dty_Type');
            if(field_type=='blocktext' || compare == 'count') field_type = 'freetext';

            if(this.options.rty_ID>0){
                ed_options['rectypeID'] = this.options.rty_ID;
            }else{
                
                    let dtFields = {dty_Type:field_type, 
                                    rst_DisplayName: $Db.dty(dty_ID,'dty_Name'),
                                    rst_FilteredJsonTermIDTree: $Db.dty(dty_ID,'dty_JsonTermIDTree'),
                                    rst_PtrFilteredIDs: $Db.dty(dty_ID,'dty_PtrTargetRectypeIDs'),
                                    rst_MaxValues:100};
                    
                    ed_options['dtFields'] = dtFields;
            }
            ed_options['detailtype'] = (field_type=='blocktext' || field_type=='file')?'freetext':field_type;
            ed_options['dtID'] = dty_ID;
            
            ed_options['language'] = (field_type=='enum') ? this.options.language : ''; // show translated terms
            
            if(field_type=='enum' && (this.options.enum_field!=null ||
                (this.options.enum_field==null && !(compare=='' || compare=='=' || compare=='-') ))){

                ed_options['detailtype'] = 'freetext';
            }

        }
        else{        
            //non base fields inputs

            if(!field_type){

                field_type = 'freetext';

                //create input element 
                if(dty_ID=='added' ||
                    dty_ID=='modified'){

                    field_type = 'date';

                }else if (dty_ID=='addedby' ||
                    dty_ID=='owner' ||
                    dty_ID=='user'){
                        //user selector
                        field_type = 'user';

                }else  if (dty_ID=='ids'){
                    
                    field_type = dty_ID;

                }else  if (dty_ID=='access' || 
                           dty_ID=='tag'){
                        
                        field_type = 'enum';
                        ed_options['dtID'] = dty_ID;
                }
            }
            
            let dtFields = {dty_Type:field_type, rst_DisplayName:'', rst_MaxValues:100};

            if(field_type=="rectype"){
                dtFields['cst_EmptyValue'] = window.hWin.HR('Any record type');
            }else if(field_type == 'user'){
                dtFields['rst_FieldConfig'] = {
                    mode: 'all_users_and_groups'
                };
            }

            ed_options['dtFields'] = dtFields;
        }//========

        
        let eqopts = [];

        if(field_type=='geo'){

            eqopts = [{key:'',title:'within'}];

        }else if(field_type=='enum' || field_type=='resource' || field_type=='relationtype' //|| field_type=='relmarker' 
                 || field_type=='user' || field_type=='access' || field_type=='ids'){

            eqopts = [{key:'',title:'equals'},
                      {key:'-',title:'not equals'}];   //- negate
                      
            if(this.options.enum_field!=null){
                eqopts[0].key = '=';
                eqopts.unshift({key:'',title:'like'}); //string match
            }

        } else if(field_type=='float' || field_type=='integer'){

            //???less than or equals, greater than or equals
            
            eqopts = [
                {key:'=',title:'equals'},
                {key:'-',title:'not equals'},
                {key:'>=',title:'>='},
                {key:'<=',title:'<='}];
/*                
                {key:'>',title:'greater than'},
                {key:'<',title:'less than'},
                {key:'<>',title:'between'},
                {key:'-<>',title:'not betweeen'}
*/
        }else if(field_type=='date'){
            //
            eqopts = [
                {key:'<>',title:'fall in/overlaps'}, //<> overlaps for range only
                {key:'><',title:'between'},  //for range only
                {key:'=',title:'exact'},     //either start or end exact to specified date
                {key:'>=',title:'after than'},
                {key:'<=',title:'before than'}];
/*                
                {key:'-',title:'not equals'},
*/
            
        }else if(field_type=='tag'){

            eqopts = eqopts.concat([
                {key:'=',title:'equals (exact)'},    //cs
                {key:'',title:'string match'},
                {key:'starts',title:'starts with'},
                {key:'ends',title:'ends with'}
                ]);
            
        }else if(dty_ID == 'exists'){
            eqopts = [{key: 'any', title: 'exists'}];
        }else{

/*        
Text:         String match, All words, Any word, No word, 
         <separator> Whole value, Starts with, Ends with 
        (I do not know what "between" does)
String match = LIKE
All words  = MATCH (field) AGAINST ('+MySQL +YourSQL' IN BOOLEAN MODE);
Any word =  OR   AGAINST ('MySQL YourSQL');
No word = None of the words is present AGAINST ('-MySQL -YourSQL' IN BOOLEAN MODE);
Whole value = EQUAL
    Any value = any value matches (current default for blank value)
    No data = no data recorded (record missing the field)
*/        
            eqopts = [
                {key:'',title:'string match'}, //case sensetive ==
                {key:'=',title:'whole value'}    //cs
            ];

            if(field_type!='file' && (dty_ID>0 || dty_ID=='title' || dty_ID=='anyfield')){
                eqopts = eqopts.concat([
                    {key:'@++',title:'all of the words'}, //full text
                    {key:'@',title:'any of the words'},  //full text
                    {key:'@--',title:'none of the words'}   //full text
                    ]);
            }
            
            eqopts = eqopts.concat([
                {key:'starts',title:'starts with'},
                {key:'ends',title:'ends with'}
                //{key:'<>',title:'between'}
                ]);
                
            if(field_type=='file'){
            eqopts = eqopts.concat([
                {key:'<=^',title:'size(kb)<='},
                {key:'>=^',title:'size(kb)>='}
                ]);
            }else if(dty_ID=='url'){
                eqopts.push({key:'-',title:'not equals'}); // - negate
            }
        }

        if( dty_ID=='notes' || dty_ID=='url'
            || (dty_ID>0 && (field_type!='enum' || this.options.enum_field==null)))
        {  // && field_type!='relmarker'
            eqopts.push({key:'', title:'──────────', disabled:true});
            eqopts.push({key:'any', title:'any value (exists)'});
            if(field_type!='relationtype'){
                eqopts.push({key:'NULL', title:'no data (missing)'});

                // Field count filtering
                eqopts.push({key:'count', title:'count of values'});
            }
        }

        this._off( this.select_conjunction, 'change');
        this._off( this.select_comparison, 'change');
        
        let prev_opt = this.select_comparison.val();

        window.hWin.HEURIST4.ui.createSelector(this.select_comparison.get(0), eqopts);
        
        if(prev_opt) this.select_comparison.val(prev_opt);
        if(this.select_comparison.get(0).selectedIndex<0){
            this.select_comparison.get(0).selectedIndex = 0;
        }

        this._on( this.select_conjunction, { change: function(){
            this._manageConjunction();
            if(window.hWin.HEURIST4.util.isFunction(this.options.onchange)){
                    this.options.onchange.call(this);
            }
        }});
        
        this._on( this.select_comparison, { change: function(){

            let cval = this.select_comparison.val();
            if(cval=='NULL' || cval=='any' || cval=='count' ){
                if(this._predicate_reltype_ele) this._predicate_reltype_ele.css('visibility', 'hidden');
                this._predicate_input_ele.css('visibility', (cval=='count' ? 'visible' : 'hidden'));
                this._predicate_input_ele.find('.editint-inout-repeat-button').parent().css('visibility', 'hidden');
                this.select_conjunction.css('visibility', 'hidden');
                this.cb_negate.hide();
            }else{
                if(this._predicate_reltype_ele) this._predicate_reltype_ele.css('visibility', 'visible');
                this._predicate_input_ele.css('visibility', 'visible');
                this._predicate_input_ele.find('.editint-inout-repeat-button').parent().css('visibility', 'visible');
                this._manageConjunction();
               
            }
            if(cval=='@' 
                || field_type=='geo' || field_type=='float' || field_type=='integer'){
                this.cb_negate.hide();
            }
            
            if(cval=='<>' || cval=='-<>' || cval=='><'){
                this._predicate_input_ele.editing_input('setBetweenMode', true);        
            }else{
                this._predicate_input_ele.editing_input('setBetweenMode', false);        
            }
            
            if(cval == 'count'){ // force freetext field on field count
                if(this._predicate_input_ele.find('.input-div > input').length == 0){
                    this._onSelectField();
                }
            }else if(field_type=='enum' && this.options.enum_field==null && cval != 'any' && cval != 'NULL'){
                //this.options.enum_field=='term' && cval != 'any' && cval != 'NULL'  &&
                
                let need_select = (cval=='=' || cval=='-' || cval=='');

                if(( (!need_select) && this._predicate_input_ele.find('.input-div > input').length == 0) ||
                    need_select && this._predicate_input_ele.find('.input-div > select').length == 0){ 
                    // check that input is correct version (text input or dropdown)

                    this._onSelectField();
                }
            }
            
            // Add help text
            if(cval == 'count'){
                this._predicate_input_ele.find('.heurist-helper1').text('Use n <n >n n1<>n2, where n is the count');
            }else{
                this._predicate_input_ele.find('.heurist-helper1').text('');
            }
            
            if(window.hWin.HEURIST4.util.isFunction(this.options.onchange)){
                    this.options.onchange.call(this);
            }
            
        } });
            
        this._current_field_type = field_type;
        //clear input values
        let prev_value = [''], prev_type = null;
        if(this._predicate_input_ele){
            
            if(this._predicate_input_ele.editing_input('instance')){
               prev_value = $(this._predicate_input_ele).editing_input('getValues');    
               prev_type = $(this._predicate_input_ele).editing_input('getDetailType');    
            }
            
            this.select_conjunction.appendTo(this.sel_container); //back to selcontainer
            this._predicate_input_ele.remove(); this._predicate_input_ele = null;    
        }
        if(this._predicate_reltype_ele){
            this._predicate_reltype_ele.remove(); this._predicate_reltype_ele = null;    
        }
        this.values_container.empty();
        
        if(field_type=='relmarker'){
            // for this type we create two elements 
            // relation type selector and resource (record pointer) record selector
            ed_options['detailtype'] = 'relationtype';
            ed_options['dtID'] = 'r';
            let dtFields = {dty_Type:'relationtype', 
                            rst_DisplayName: $Db.dty(dty_ID,'dty_Name'),
                            rst_FilteredJsonTermIDTree: $Db.dty(dty_ID,'dty_JsonTermIDTree'),
                            rst_DefaultValue: '',
                            rst_MaxValues:100};
            ed_options['dtFields'] = dtFields;
            
            this._predicate_reltype_ele = $("<div>").editing_input(ed_options).appendTo(this.values_container);
            let ele = this._predicate_reltype_ele.find('.editint-inout-repeat-button')
                        .css({'margin-left':'22px','min-width':'16px'});
            ele = ele.parent();
            ele.css('min-width','44px');

            ed_options['detailtype'] = 'resource';
            ed_options['dtID'] = dty_ID;
            ed_options['dtFields'] = null;
            
        }else if(field_type=='resource'){ 
            
            if(this.options.reverse_RtyID>0){
                ed_options.dtFields = {dty_Type:'resource', 
                            rst_DisplayName: 'AAAAA',
                            rst_PtrFilteredIDs: ''+this.options.reverse_RtyID};
                
            }else {
                let ptr_field = null;
                if(ed_options['rectypeID']){
                    ptr_field = $Db.rst(ed_options['rectypeID'], ed_options['dtID']);
                }
                if(ptr_field){
                    ed_options.dtFields = window.hWin.HEURIST4.util.cloneJSON(ptr_field);
                }else{
                    ed_options.dtFields = window.hWin.HEURIST4.util.cloneJSON($Db.dty(ed_options['dtID']));
                }
                ed_options.dtFields['rst_PtrFilteredIDs'] = $Db.dty(ed_options['dtID'], 'dty_PtrTargetRectypeIDs');
            }
            
            ed_options.dtFields['rst_CreateChildIfRecPtr'] = 0;
            ed_options.dtFields['rst_DefaultValue'] = '';
            ed_options.dtFields['rst_PointerMode'] = 'browseonly';
        }else if(field_type=='freetext' || field_type=='blocktext' || field_type==prev_type){
            ed_options.values = prev_value;
        }

        //init input elements
        this._predicate_input_ele = $("<div>")
            .editing_input(ed_options).appendTo(this.values_container);
            
        //transfer conjunction to input element
        let ele = this._predicate_input_ele.find('.editint-inout-repeat-button')
                    .css({'margin-left':'22px','min-width':'16px'});

        ele = ele.parent();
        ele.css('min-width','44px');
        this.select_conjunction.appendTo(ele);
        this.select_conjunction.hide();
            
        this.select_comparison.trigger('change');
    },

    //
    //
    //
    getCodes: function(){
        let codes = this.options.code.split(':');
        codes[codes.length-1] = this.options.dty_ID
        
        return codes.join(':');
    },
    
    //
    //
    //
    getValues: function(){
        if(this._predicate_input_ele){
            
            let relatype_vals = null;
            let has_relatype_value = false, has_value = false;
            let vals = $(this._predicate_input_ele).editing_input('getValues');
            let isnegate =  this.cb_negate.is(':visible') && 
                            this.cb_negate.find('input').is(':checked');
            let op = this.select_comparison.val();

            let lang_code = this.options.language;

            if(this._current_field_type=='relmarker'){
                relatype_vals = $(this._predicate_reltype_ele).editing_input('getValues');
                has_relatype_value = (relatype_vals.length>1 ||!window.hWin.HEURIST4.util.isempty(relatype_vals[0]));
            }
            has_value =  (vals.length>1 || !window.hWin.HEURIST4.util.isempty(vals[0]));
            
            if (!(has_relatype_value || has_value) && !(op=='any' || op=='NULL')){
                return null;
            }            

// 2023-07-16 dropdown is not used for trm_Label            
/*
            if(this._current_field_type=='enum' && this.options.enum_field=='term' && 
                this._predicate_input_ele.find('.input-div > select').length > 0){ // change from ids to label

                for (let i = 0; i < vals.length; i++) {
                    let def_label = $Db.trm(vals[i], 'trm_Label');
                    vals[i] = $Db.trm_getLabel(vals[i], this.options.language);

                    if(lang_code == 'ALL' || vals[i] != def_label){ // prepend language code
                        vals[i] = lang_code + ':' + vals[i];
                    }
                }
            }
*/

            if(op=='any'){
                    op = '';
                    vals = [''];
            }else if(op=='NULL'){
                    op = '';
                    vals = ['NULL'];
            }else if( ( (this._current_field_type=='enum'  && this.options.enum_field==null)
                        || this._current_field_type=='relationtype' 
                        || this._current_field_type=='ids'
                        || this._current_field_type=='user'
                        || this._current_field_type=='resource') 
                    && vals.length>1 && this.select_conjunction.val()=='any')
            {
                vals = [(isnegate?'-':'')+vals.join(',')];

            }else if (this._current_field_type=='relmarker') {
                
                
                if(has_relatype_value){
                    if(has_value){
                        vals = [{ids:(isnegate?'-':'')+vals.join(',')}];
                    } else{
                        vals = [];
                    }
                    vals.push({r:(isnegate?'-':'')+relatype_vals.join(',')});
                }else{
                    vals = (isnegate?'-':'')+vals.join(',');    
                }
                
                return {related_to:vals};
                
            }else {
                
                if(op=='starts'){
                    op = '';
                    $.each(vals,function(i,val){vals[i]=vals[i]+'%'});
                }else if(op=='ends'){
                    op = '';
                    $.each(vals,function(i,val){vals[i]='%'+vals[i]});
                }else if (op=='<>' || op=='count'){
                    op = '';
                }else if (op=='-<>'){
                    op = '-';
                }
                
                if(this._current_field_type=='enum'
                    && (this.options.enum_field == 'term' || this.options.enum_field == 'desc') 
                    && lang_code != ''){
                    // prepend language code
                    $.each(vals,function(i,val){vals[i]=op+lang_code+':'+vals[i]});
                }else if(op!=''){
                    $.each(vals,function(i,val){vals[i]=op+vals[i]});        
                }

            }

            let key;
            let org_op = this.select_comparison.val();
            
            if (this._current_field_type=='geo') {
                key = 'geo';    
            }else 
            if(this.options.dty_ID.indexOf('r.')==0){
                key = 'r:'+this.options.dty_ID.substr(2); 
            }else    
            if(this.options.dty_ID>0){
                key = (org_op!='count' ? 'f:' : 'fc:') + this.options.dty_ID;
                
                if(this.options.enum_field!=null){
                    key = key + ':' + this.options.enum_field;
                }
                
            }else 
            if(this.options.dty_ID=='anyfield' || this.options.dty_ID==''){
                key = 'f';
            }else
            if(this.options.dty_ID=='typeid' || this.options.dty_ID=='typename'){
                key = 't';
            }else {
                key = (org_op != 'count' ? '' : 'fc:') + this.options.dty_ID;
            }
            
            let res = {};
            
            if(vals.length==1){
                res = {};
                res[key] = vals[0];     
            }else{
                let conj = this.select_conjunction.val();
                if(key=='tag'){
                    let p = {}; 
                    p[conj] = vals;
                    res[key] = p;
                }else{
                    res[conj] = [];
                    $.each(vals,function(i,val){ 
                        let p = {}; 
                        p[key] = val;
                        res[conj].push(p); 
                    });        
                }
                
            }
          
            return res;  
        }else{
            return null;
        }
    },
    
    //
    //
    //    
    _onSelectField:function(){

        if(!(this.options.top_rty_ID>0)){        
            this.options.dty_ID = this.select_fields.val();
        }
            
        this._defineInputElement();
        
    },
    

    _manageConjunction: function()
    {                
        this.select_conjunction.parent().find('.conj').remove(); //previous
        let ft = this._current_field_type;

        let eles = !this._predicate_input_ele?[]:this._predicate_input_ele.find('.input-cell > .input-div');
        let cnt = eles.length;
        
        if(ft=='user' ||  ft=='ids' || cnt<2){
            if(ft=='user' ||  ft=='ids'){
                this.select_conjunction.val('any');    
            }
            this.select_conjunction.hide();
        }else{
            this.select_conjunction.css('visibility', 'visible');
            this.select_conjunction.show();
            
            let is_any = (this.select_conjunction.val()=='any');
            
            if(is_any){
                this.select_conjunction.css({'margin':'10px 0px 2px 8px',width:'33px'});
            }else{
                this.select_conjunction.css({'margin':'10px 0px 2px 0',width:'44px'});
            }

            //add or/and
            if(cnt>2){

                let mh = $(eles[0]).height();

                cnt = cnt-2;
                eles = [];
                while(cnt--) eles.push('<div class="conj" style="line-height:'+(mh+1)+'px;padding:0px '
                            +(is_any?12:5)+'px">'
                    +(is_any?'or':'and')
                    +'</div>');

                $(eles.join('')).appendTo(this.select_conjunction.parent());
            }

        }
    }                
        

});
