/**
* searchBuilderSort.js - element in filter builder - to define query element
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

$.widget( "heurist.searchBuilderSort", {

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
     linked_to,linkedfrom,related_to,relatedfrom,links: various link predicates 
*/    
    
    // default options
    options: {
        rty_ID: 0,
        
        // callback
        onremove: null,
        onchange: null
    },

    // the widget's constructor
    _create: function() {

        let that = this;
        
        //create elements for predicate
        // 1. field selector for field or links tokens
        // 2. order
        
        // selector container - for fields and comparison
        this.sel_container = $('<div>')
            .css({'display':'inline-block','vertical-align':'top','padding-top':'3px'})
            .appendTo(this.element);
            
        $('<div class="header_narrow sort_header" '
        +'style="min-width:90px;display:inline-block;text-align:right;padding-right: 9px;">'
        +'<label for="opt_rectypes">Sort by</label></div>')
            .appendTo( this.sel_container );
            
        // 2. field selector for field or links tokens
        this.select_fields = $( '<select>' )
            .attr('title', 'Select field' )
            .addClass('text ui-corner-all')
            .css({'margin-left':'2em','min-width':'210px','max-width':'210px'})
            .appendTo( this.sel_container );

        // 3. comparison selector or relationtype selector
        this.select_order = $( '<select>' )
            .attr('title', 'Select order' )
            .addClass('text ui-corner-all')
            .css({'margin':'0 1em','min-width':'90px', border:'none'})
            .appendTo( this.sel_container );

        let topOptions3 = [
                {key:'0', title: 'Ascending (A..Z, 1..9)'},
                {key:'1', title: 'Descending (Z..A, 9..1)'}];

        window.hWin.HEURIST4.ui.createSelector(this.select_order.get(0), topOptions3);

        // 1. Remove icon
        this.remove_token = $( "<span>" )
        .attr('title', 'Remove this sort token' )
        .addClass('ui-icon ui-icon-circle-b-close')
        .css({'cursor':'pointer','font-size':'0.8em',visibility:'hidden'})
        .appendTo( this.sel_container );        

        this._on( this.remove_token, { click: function(){
            if(window.hWin.HEURIST4.util.isFunction(this.options.onremove)){
                this.options.onremove.call(this);
            }    
        } });
        
        this.sel_container
        .on( 'mouseenter', function(event){ 
            that.remove_token.css({visibility:'visible'});
        } )
        .on( 'mouseleave', function(event){
                   that.remove_token.css({visibility:'hidden'});
        } );


        this._on( this.select_order, { change: function(){
                if(window.hWin.HEURIST4.util.isFunction(this.options.onchange))
                {
                    this.options.onchange.call(this);
                }
        }});




            
        this._refresh();
        
    }, //end _create
    
    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        
            let topOptions2 = [
                    {key:'', title:window.hWin.HR("select...")},
                    {key:'t', title:window.hWin.HR("record title")},
                    {key:'id', title:window.hWin.HR("record id")},
                    {key:'rt', title:window.hWin.HR("record type")},
                    {key:'u', title:window.hWin.HR("record URL")},
                    {key:'m', title:window.hWin.HR("date modified")},
                    {key:'a', title:window.hWin.HR("date added")},
                    {key:'r', title:window.hWin.HR("personal rating")},
                    {key:'p', title:window.hWin.HR("popularity")}];
                    
            let allowed_fieldtypes = ['enum','freetext','blocktext','year','date','integer','float','resource'];
            
            //show field selector
            window.hWin.HEURIST4.ui.createRectypeDetailSelect(this.select_fields.get(0), this.options.rty_ID, 
                        allowed_fieldtypes, topOptions2, 
                        {useHtmlSelect:false, selectedValue:'t'});                
            
            this._on( this.select_fields, { change: function(){
                    if(window.hWin.HEURIST4.util.isFunction(this.options.onchange))
                    {
                        this.options.onchange.call(this);
                    }
            }});
            
            
    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

    },
    
    //
    //
    //
    getValue: function(){
        
        let key = this.select_fields.val();
        
        if(key && key>0){
            key = 'f:'+key;
        }
        if(key && this.select_order.val()=='1'){
            key = '-'+key;
        }
        return key;
    },

    // Get label of selected value
    getLabel: function(){

        let label 
        if(this.select_fields.hSelect('instance')){
            label = this.select_fields.hSelect('widget').text();    
        }
        if(label == 'select...' || label == ''){
            return '';
        }

        return label;
    }
});
