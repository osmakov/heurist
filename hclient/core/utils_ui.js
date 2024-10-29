/**
*  Utility functions 
* a) to create standard record types, field types and terms selectors
* b) fast access to db structure defintions
*
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global showLoginDialog,editSymbology,imgFilter,editTheme,HPublishDialog */

/*
Selectors:
addoption - helper function to add option to select element
createSelector - create SELECT element (if selObj is null) and fill with given options


createVocabularySelect - creatres selector with vocabularies only (top level terms)
createTermSelect - create terms selector for given vocabulary


createRectypeGroupSelect - get SELECT for record type groups
createDetailtypeGroupSelect
createVocabularyGroupSelect

createRectypeSelect - get SELECT for record types   
createRectypeDetailSelect - get SELECT for details of given recordtype
    
createUserGroupsSelect - get SELECT for list of given groups, othewise loads list of groups for current user    

setValueAndWidth assign value to input and adjust its width

initHSelect - converts HTML select to jquery selectmenu

getRecordTitle - retuns of title for given record id
createTemplateSelector - fills with names of smarty templates

createLanguageSelect - fill html select with list of languages for translation 

ENTITY

openRecordEdit  - open add/edit record form/dialog
openRecordInPopup - open viewer or add/edit record form
createRecordLinkInfo - creates ui for record pointer or relationship record

createEntitySelector - get id-name selector for specified entity
showEntityDialog - entity editor/selector dialog

showPublishDialog


Other UI functions    
initDialogHintButtons - add show hint and context buttons into dialog header
initHelper - Inits helper div (slider) and button   


createRecordLinkInfo - return ui for link and relationship

onInactive invokes cb after ms user inactivity

For mapping:

prepareMapSymbol

showEditSymbologyDialog
showImgFilterDialog
*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.ui) 
{

window.hWin.HEURIST4.ui = {
    
    isVisible: function(ele){
        return ele && $(ele).is(':visible');
    },
    
    setValueAndWidth: function(ele, value, padding){
        
        if(window.hWin.HEURIST4.util.isempty(value)) value='';
        ele = $(ele);
        if(ele.is('input')){
            
            if(!(padding>0)) padding = 4;
            
            ele.val( value )
                .css('width', (Math.min(80,Math.max(20,value.length))+padding+'ex'));
        }else{
            ele.html( value );
        }
    },
    
    //
    // helper function to add option to select element
    //
    addoption: function(sel, value, text, disabled, selected, hidden)
    {
        let option = document.createElement("option");
       
        option.text = text; 
        option.value = value;
        if(disabled===true){
            option.disabled = true;
        }
        if(selected===true){
            option.selected = true;
        }
        if(hidden===true){
            option.hidden = true;
        }
       
        sel.appendChild(option);
        /*
        try {
            // for IE earlier than version 8
            sel.add(option, sel.options[null]);
        }catch (ex2){
            sel.add(option, null);
        }
        */
        
        return option;
    },

    //
    // create checkbox, radio or select element
    // options
    //  type:
    //  hideclear 
    //  values: [{key:'',title:''},....]
    //
    createInputSelect: function($inpt, options) {
        
        if(options.type=='checkbox'){
            if($inpt==null || !$inpt.is('input')){
                $inpt = $('<input>');
            }
            $inpt.attr('type','checkbox');
            
            //@todo
            
            return $inpt;
        }else if(options.type=='radio'){
            
            let $parent = null;
            if($inpt!=null){
                $parent = $inpt.parent();
            }
            
            let $inpt_group = $('<div>').attr('radiogroup',1).uniqueId()
                        .css({background: 'none', padding: '2px'});
                        
           if($parent!=null) {
                $inpt_group.insertBefore($inpt);   
                $inpt.remove();
           }
                        
            let id = $inpt_group.attr('id');
            
            for (let idx in options.values)
            if(idx>=0){
                let key, title;
                if(window.hWin.HEURIST4.util.isnull(options.values[idx].key) && 
                   window.hWin.HEURIST4.util.isnull(options.values[idx].title))
                {
                    key = options.values[idx];
                    title = options.values[idx];
                }else{
                    key = options.values[idx].key;
                    title = options.values[idx].title;
                }
                if(!window.hWin.HEURIST4.util.isnull(title)){
                    $('<label style="padding-right:5px"><input type="radio" value="'
                            +key+'" name="'+id+'">'
                            +window.hWin.HEURIST4.util.htmlEscape(title)+'</label>').appendTo($inpt_group);
                }
            }
            
            return $inpt_group;
            
        }else { //select by default
            if($inpt==null || !$inpt.is('select')){
                $inpt = $('<select style="width:100px">').uniqueId();
            }
            if($inpt.width()<100) $inpt.width(100);
            
            return window.hWin.HEURIST4.ui.createSelector($inpt[0], options.values);
        }
        
    
    },
        
    //
    // create SELECT element (if selObj is null) and fill with given options
    // topOptions either array or string
    // [{key:'',title:''},....]
    //
    createSelector: function(selObj, topOptions) {
        if(selObj==null){
            selObj = document.createElement("select");
        }else{
            $(selObj).off('change');
            if($(selObj).hSelect("instance")!=undefined){
               $(selObj).hSelect("destroy"); 
            }
            $(selObj).empty();
        }
        
        window.hWin.HEURIST4.ui.fillSelector(selObj, topOptions);
        
        return selObj;
    },

    //
    //
    //
    fillSelector: function(selObj, topOptions) {
        
        if(Array.isArray(topOptions)){
            let idx,key,title,disabled,depth, border, selected, hidden;
            if(topOptions){  //list of options that must be on top of list
                for (idx in topOptions)
                {
                    if(idx){
                        if(window.hWin.HEURIST4.util.isnull(topOptions[idx].key) && 
                           window.hWin.HEURIST4.util.isnull(topOptions[idx].title))
                        {
                            key = topOptions[idx];
                            title = topOptions[idx];
                            disabled = false;
                            depth = 0;
                            border = false;
                            selected = false;
                            hidden = false;
                        }else{
                            key = topOptions[idx].key;
                            title = topOptions[idx].title;
                            disabled = (topOptions[idx].disabled===true);
                            depth = (topOptions[idx].depth>0)?topOptions[idx].depth:0;
                            border = (topOptions[idx].hasborder===true);
                            selected = (topOptions[idx].selected===true);
                            hidden = (topOptions[idx].hidden===true);
                        }
                        if(!window.hWin.HEURIST4.util.isnull(title))
                        {
                            if(!window.hWin.HEURIST4.util.isnull(topOptions[idx].optgroup)){
                                let grp = document.createElement("optgroup");
                                grp.label =  title;
                                selObj.appendChild(grp);
                            }else{
                                let opt = window.hWin.HEURIST4.ui.addoption(selObj, key, title, disabled, selected, hidden);
                                if(topOptions[idx].group>0){
                                    $(opt).attr('group', topOptions[idx].group);
                                }else if(depth>0){
                                    $(opt).attr('depth', depth);
                                }
                                if(border){
                                    $(opt).attr('hasborder', 1);
                                }
                            }

                        }
                    }
                }
            }
            
            
        }else if(!window.hWin.HEURIST4.util.isempty(topOptions) && topOptions!==false){
            if(topOptions===true) topOptions = '  ';  // <blank>
            window.hWin.HEURIST4.ui.addoption(selObj, '', topOptions);
        }


        return selObj;
    },    

    
    //
    // options: vocab_id, topOptions, defaultTermID, useHtmlSelect:false
    //
    createTermSelect: function(selObj, options){
      
        let vocab_id =  options.vocab_id, 
            defaultTermID =  options.defaultTermID>0?options.defaultTermID:null,
            topOptions =  options.topOptions,
            supressTermCode = options.supressTermCode,
            useHtmlSelect  = (options.useHtmlSelect===true),
            eventHandlers = options.eventHandlers,
            lang_code = options.language_code;
        
        //create selector 
        selObj = window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);
            
        let data = $Db.trm_TreeData(vocab_id, 'select', false, lang_code);                
        let termCode;
        let vcg_Trash = $Db.getTrashGroupId('vcg');
       
        //add optgroups and options
        for(let i=0; i<data.length; i++){
            
            if(data[i].is_vocab){

                if($Db.trm(data[i].key, 'trm_VocabularyGroupID') == vcg_Trash){
                    continue;
                }
                
                let opt = window.hWin.HEURIST4.ui.addoption(selObj, 
                                                    data[i].key, data[i].title);
                
                $(opt).attr('disabled', 'disabled');
                $(opt).attr('group', 1);
                
            }else{

                let vocab_id = $Db.trm(data[i].key, 'trm_Parents').split(',')[0];
                if($Db.trm(vocab_id, 'trm_VocabularyGroupID') == vcg_Trash){
                    continue;
                }
            
                if(supressTermCode || window.hWin.HEURIST4.util.isempty(data[i].code)){
                    termCode = '';
                }else{
                    termCode = " [code "+data[i].code+"]";
                }
                
                let opt = window.hWin.HEURIST4.ui.addoption(selObj, 
                                                data[i].key, data[i].title+termCode);
                $(opt).attr('depth', data[i].depth);

                if (data[i].key == defaultTermID || data[i].title == defaultTermID) {
                        opt.selected = true;
                }
            }
        }//for

        //init selectmenu
        selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, useHtmlSelect, null, eventHandlers);

        return $(selObj);
    },

    //
    // useGroups - true - grouped by vocabulary tabs, or group id
    // domain - any enum relation for useGroup = true
    //
    createVocabularySelect: function(selObj, options) {

        let defaultTermID =  options.defaultTermID,
        topOptions = options.topOptions,
        useGroups = options.useGroups;

        let domain = (options.domain=='enum' || options.domain=='relation')?options.domain:null;
        let vcg_Trash = $Db.getTrashGroupId('vcg');

        if (!(useGroups>0 || useGroups===false)){
            useGroups = true;
        }

        //vocab groups    
        let vgroups=[], vocabs = {};
        if(useGroups!==true){
            if(useGroups===false){
                vocabs['0'] = [];
                vgroups.push(0);
            }else if(useGroups>0){  //specific group only
                vocabs[useGroups] = [];
                vgroups.push(useGroups);
            }
        }

        //find all vocabularies and group them by vocab groups
        $Db.trm().each(function(trmID, record){
            let parent_id = this.fld(record, 'trm_ParentTermID');
            if(!(parent_id>0)){
                let grp_id = this.fld(record, 'trm_VocabularyGroupID');
                if(grp_id>0 && grp_id!=vcg_Trash){ 
                    if(useGroups===false){
                        vocabs['0'].push(trmID);
                    }else if(useGroups===true){
                        if(domain==null || domain==$Db.trm(trmID,'trm_Domain')){
                            if(!vocabs[grp_id]){
                                vocabs[grp_id] = [];  
                                vgroups.push(grp_id);
                            } 
                            vocabs[grp_id].push(trmID);
                        }
                    }else if(useGroups>0 && useGroups==grp_id){
                        vocabs[grp_id].push(trmID);
                    }
                }
            }
        });
        
        //sort groups by vcg_Order
        if(useGroups===true){
             
            //sort by name within group
            vgroups.sort(function(a,b){
                return $Db.vcg(a,'vcg_Order')<$Db.vcg(b,'vcg_Order')?-1:1;
            });
            
            
        }
        

        //create selector 
        selObj = window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);

        //add optgroups and options  - vgroups is sorted array
        $.each(vgroups,function(i,grp_id){

            if(useGroups===true  && grp_id>0 && vocabs[grp_id].length>1){
                //add group header
                let group_name = $Db.vcg(grp_id,'vcg_Name');
                if(!group_name) group_name = 'Group# '+grp_id+' (missing)';
                
                let opt = window.hWin.HEURIST4.ui.addoption(selObj, grp_id, group_name);
                $(opt).attr('disabled', 'disabled');
                $(opt).attr('group', 1);
            }

            //sort by name within group
            vocabs[grp_id].sort(function(a,b){
                return $Db.trm(a,'trm_Label')<$Db.trm(b,'trm_Label')?-1:1;
            });

            $.each(vocabs[grp_id],function(i,trm_id){
                let trm_name = $Db.trm(trm_id,'trm_Label');
                let opt = window.hWin.HEURIST4.ui.addoption(selObj, trm_id, trm_name);
                $(opt).attr('depth', 1);

                if (trm_id == defaultTermID || trm_name == defaultTermID) {
                    opt.selected = true;
                }

            });
        });

        //init selectmenu
        selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, false);

        return $(selObj);

    },


    //
    // get selector for record/detail/vocabulary type groups
    //
    createEntityGroupSelect: function(entity, selObj, topOptions) {

        let recset = $Db[entity]();
        let options = recset.makeKeyValueArray(entity+'_Name');
        let trash_id = $Db.getTrashGroupId(entity);

        for(let idx=1; idx<options.length; idx++){
            if(trash_id != options[idx]['key']){ break; }

            options.splice(idx, 1); // remove Trash group
        }

        if(!(Array.isArray(topOptions) ||
           window.hWin.HEURIST4.util.isempty(topOptions) ||
           topOptions===false)){
            if(topOptions===true) topOptions = '  ';  // <blank>
            topOptions = [{key:'', title:topOptions}];
        }

        if(topOptions){
            options = topOptions.concat(options);
        } 
        
        window.hWin.HEURIST4.ui.createSelector(selObj, options);
        
        return selObj;
    },

    createRectypeGroupSelect: function(selObj, topOptions) {
        return window.hWin.HEURIST4.ui.createEntityGroupSelect('rtg', selObj, topOptions);
    },
    
    createDetailtypeGroupSelect: function(selObj, topOptions) {
        return window.hWin.HEURIST4.ui.createEntityGroupSelect('dtg', selObj, topOptions);
    },
    
    createVocabularyGroupSelect: function(selObj, topOptions) {
        return window.hWin.HEURIST4.ui.createEntityGroupSelect('vcg', selObj, topOptions);
    },

    //
    // get selector for record types
    //
    // rectypeList - constraint options to this list, otherwise show entire list of rectypes separated by groups
    //
    createRectypeSelect: function(selObj, rectypeList, topOptions, useHtmlSelect) {

            return window.hWin.HEURIST4.ui.createRectypeSelectNew(selObj, 
                {rectypeList:rectypeList, topOptions:topOptions, useHtmlSelect:useHtmlSelect});
    },
    
    createRectypeSelectNew: function(selObj, options) {
        
        let rectypeList = options.rectypeList;
        let topOptions = options.topOptions;
        let useHtmlSelect = (options.useHtmlSelect===true);
        let useIcons = (options.useIcons===true); //!useHtmlSelect && 
        let useCounts = (options.useCounts===true);
        let useGroups = (options.useGroups!==false);
        let useIds = (options.useIds===true);
        let useCheckboxes = (options.useCheckboxes===true);
        let eventHandlers  = options.eventHandlers;
        
        let showAllRectypes = (options.showAllRectypes===true); //otherwise only non-hidden
 
        selObj = window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);

        useHtmlSelect = (useHtmlSelect===true);
        
        //recordset 
        let rtg_Trash = $Db.getTrashGroupId('rtg');
        let rectypes = $Db.rty().getSubSetByRequest({ 'sort:rty_Name':1, 'rty_RecTypeGroupID': `!=${rtg_Trash}` });

        if(rectypes){ 

        if(!window.hWin.HEURIST4.util.isempty(rectypeList)){
            
            if(!Array.isArray(rectypeList)){
                if(rectypeList>0){
                    rectypeList = [rectypeList];
                }else{
                    rectypeList = rectypeList.split(',');
                }
            }
        }else if(!useGroups){ //all rectypes however plain list (not grouped)
            rectypeList = rectypes.getIds();
        }else{
            rectypeList = []; //all rectypes
        }
        
        if(!useGroups || (rectypeList.length>0 && rectypeList.length<7)){  //show only specified list of rectypes
        
            if(useCounts){//sort by count
                rectypeList.sort(function(a,b){
                     let ac = $Db.rty(a,'rty_RecCount');   
                     let bc = $Db.rty(b,'rty_RecCount');   
                                        
                     if(isNaN(ac)) ac = 0;
                     if(isNaN(bc)) bc = 0;
                     return Number(ac)<Number(bc)?1:-1;
                });
            }
            
            let isEmpty = true;
        
            for (let idx in rectypeList)
            {
                if(idx){
                    const rectypeID = rectypeList[idx];
                    const name = window.hWin.HEURIST4.util.htmlEscape($Db.rty(rectypeID,'rty_Name'));
                    if(!window.hWin.HEURIST4.util.isnull(name))
                    {

                        let rty_Count = 0;
                        if(useCounts){
                            rty_Count = $Db.rty(rectypeID,'rty_RecCount');
                            if(isNaN(rty_Count) || rty_Count<1) continue;
                        }
                        
                        isEmpty = false;
                       
                        let opt = window.hWin.HEURIST4.ui.addoption(selObj, rectypeID, name);
                        
                        if(useIcons){
                            $(opt).attr('icon-url', (window.hWin.HAPI4.iconBaseURL + rectypeID));
                        }
                        if(useCounts){
                            $(opt).attr('rt-count', rty_Count);
                        }
                        if(useIds){
                            $(opt).attr('entity-id', rectypeID);
                        }
                    }
                }
            }
            
            if(useCounts && isEmpty){
                window.hWin.HEURIST4.ui.addoption(selObj, 0, 'No appropriate records in the database')
            }
            
        }else{  //show rectypes separated by groups
        
            let groups = {};
            let groups_order = $Db.rtg().getSubSetByRequest({ 'sort:rtg_Order':1 });
            
            groups_order.each(function(rtgID, record){
                groups[rtgID] = {title:$Db.rtg(rtgID,'rtg_Name'),rty:[]};
            });
            
            //scan all rectypes and group by rtg groups        
            rectypes.each(function(rtyID, record){
                
                if ((rectypeList.length==0 
                    || window.hWin.HEURIST4.util.findArrayIndex(rtyID, rectypeList)>=0)
                    &&
                    (showAllRectypes || $Db.rty(rtyID,'rty_ShowInLists')==1)){
                        
                    let grp_id = $Db.rty(rtyID,'rty_RecTypeGroupID');
                    if(groups[grp_id] && groups[grp_id]['rty']){
                        groups[grp_id]['rty'].push(rtyID);
                    }else{
                        console.error('Group ',grp_id,'not found for record type ',rtyID);
                    }
                        
                }
                
            });
            
            groups_order = groups_order.getOrder();
            
            for(let k=0; k<groups_order.length; k++){
                
                let rtgID  = groups_order[k];
                if(groups[rtgID].rty.length>0){
                
                    //add header    
                    if(useHtmlSelect){
                        let grp = document.createElement("optgroup");
                        grp.label = groups[rtgID].title;
                        selObj.appendChild(grp);
                    }else{
                        let opt = window.hWin.HEURIST4.ui.addoption(selObj, 0, groups[rtgID].title);
                        $(opt).attr('disabled', 'disabled');
                        $(opt).attr('group', 1);
                    }


                    //add rectypes
                    for (let i=0; i<groups[rtgID].rty.length; i++){
                        
                        const rtyID = groups[rtgID]['rty'][i];
                        const name = window.hWin.HEURIST4.util.htmlEscape($Db.rty(rtyID, 'rty_Name'));

                        let opt = window.hWin.HEURIST4.ui.addoption(selObj, rtyID, name);
                        $(opt).attr('depth', 1);
                        $(opt).attr('title', $Db.rtg(rtyID, 'rty_Description'));
                        
                        
                        if(useIcons){
                            $(opt).attr('icon-url', (window.hWin.HAPI4.iconBaseURL + rtyID));
                        }
                        if(useCounts){
                            $(opt).attr('rt-count',$Db.rty(rtyID, 'rty_RecCount'));
                        }
                        if(useIds){
                            $(opt).attr('entity-id', rtyID);
                        }
                        if(useCheckboxes){
                            let r = window.hWin.HEURIST4.util.findArrayIndex(rtyID, options.marked);
                            $(opt).attr('rt-checkbox', (r>=0)?1:0);
                            $(opt).attr('data-id', rtyID);
                        }
                    }
                    
                }
            }
            
 
            if(rectypeList.length>0){
                $(selObj).val(rectypeList[0]);
            }
            
        }
        }
        
        selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, useHtmlSelect, null, eventHandlers);

        return $(selObj);
    },
    
    /**
    * get SELECT for details of given recordtype
    *
    * rtyIDs - record type ID otherwise returns all field types grouped by field groups
    * allowedlist - of data types to this list 
    * 
    * usage search.js, recordAction.js
    */
    createRectypeDetailSelect: function(selObj, rtyIDs, allowedlist, topOptions, options ) {

        window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);
        
        let showDetailType = false;
        let addLatLongForGeo = false;
        let requriedHighlight = false;
        let selectedValue = null;
        let show_parent_rt = false; //show parent entity field
        let useHtmlSelect = true;
        let useIds = false;
        let initial_indent = 0;
        let eventHandlers = null;
        let extraOptions = {};
        if(options){  //at the moment it is implemented for single rectype only
            showDetailType    = options['show_dt_name']==true;
            addLatLongForGeo  = options['show_latlong']==true;
            requriedHighlight = options['show_required']==true;
            selectedValue     = options['selectedValue'];
            show_parent_rt    = options['show_parent_rt']==true;
            initial_indent    = options['initial_indent']>0?options['initial_indent']:0;
            useHtmlSelect     = options['useHtmlSelect']!==false;
            useIds            = options['useIds']===true;
            eventHandlers     = options['eventHandlers'];
            extraOptions      = options['extraOptions'] ? options['extraOptions'] : {};
        }
        
        let details;
     
        //show fields for specified set of record types
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(rtyIDs) && rtyIDs.length>1){
            //get fields for specified array of record types
            // if number of record types is less than 10 form structure
            // name of field as specified in detailtypes
            //       names of field as specified in record structure (disabled items)
            let dtys = {}, dtyNames = [],dtyNameToID = {},dtyNameToRty={};
            
            let rty,rtyName,dtyName,fieldName,opt;
            
            //for all rectypes find all fields as Detail names sorted
            for (let i in rtyIDs) {
                rty = rtyIDs[i];
                rtyName = $Db.rty(rty, 'rty_Name');
                
                let dtFields = $Db.rst(rty);
                
                dtFields.each2(function(dty, detail){
                    
                      let field_type = $Db.dty(dty, 'dty_Type');
                      if(allowedlist!=null && 
                         allowedlist.indexOf(field_type)<0){
                         //not allowed - skip
                         
                      }else{  
                      
                          dtyName = $Db.dty(dty, 'dty_Name');
                          if (!dtys[dtyName]){
                            dtys[dtyName] = [];
                            dtyNameToID[dtyName] = dty;
                            dtyNameToRty[dty] = rty; //not used
                            dtyNames.push(dtyName);
                          }
                          fieldName = rtyName + "." + detail['rst_DisplayName'];
                          dtys[dtyName].push(fieldName);
                      }
                });
                
            }//for rectypes
            
            //fill select
            if (dtyNames.length >0) {
                
                //sort by name - case insensitive
                dtyNames.sort(function(a, b) {
                    let nameA = a.toLocaleUpperCase(); // ignore upper and lowercase
                    let nameB = b.toLocaleUpperCase(); // ignore upper and lowercase
                    return nameA.localeCompare(nameB);
                });
                //add option for DetailType enabled followed by all Rectype.Fieldname options disabled
                for (let i in dtyNames) {
                  dtyName = dtyNames[i];
                  const dtyID = dtyNameToID[dtyName];
                  
                  opt = window.hWin.HEURIST4.ui.addoption(selObj, dtyID, dtyName); //dtyNameToRty[dtyID]+'-'+
                    
                  if(useIds){
                    $(opt).attr('entity-id', dtyID);
                  }
                  
                  //sort RectypeName.FieldName
                    dtys[dtyName].sort(function(a, b){
                        let nameA = a.toLocaleUpperCase(); // ignore upper and lowercase
                        let nameB = b.toLocaleUpperCase(); // ignore upper and lowercase
                        return nameA.localeCompare(nameB);
                    });
                  
                  for (let j in dtys[dtyName]){
                    fieldName = dtys[dtyName][j];
                    
                    opt = window.hWin.HEURIST4.ui.addoption(selObj, '',  fieldName);
                    $(opt).attr('depth',1);
                   
                    opt.disabled = "disabled";
                  }
                }
            }else{
                window.hWin.HEURIST4.ui.addoption(selObj, '', window.hWin.HR('no suitable fields'));
            }
            
        }else 
        //details for the only recordtype
        if((window.hWin.HEURIST4.util.isArrayNotEmpty(rtyIDs) && rtyIDs.length==1) || Number(rtyIDs)>0){
            
            let rectype = Number((Array.isArray(rtyIDs))?rtyIDs[0]:rtyIDs);
            
            details = $Db.rst(rectype);
            if(!details) return selObj; //structure not defined
                
            let arrterm = [];
            rectype = ''+rectype;
            
            if(show_parent_rt){
                let DT_PARENT_ENTITY  = window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY'];
                //get all child rectypes
                let all_structs = $Db.rst_idx2();
                for (let rty_ID in all_structs){
                    let recset = all_structs[rty_ID];
                    recset.each2(function(dty_ID, record){
                    
                        if(record['rst_CreateChildIfRecPtr']==1){
                            let fieldtype = $Db.dty(dty_ID, 'dty_Type');
                            let constraint = $Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs');
                            if(fieldtype=='resource' && constraint && constraint.split(',').indexOf(rectype)>=0){
                                
                                    let name = 'Parent record ('+$Db.rty(record['rst_RecTypeID'], 'rty_Name')+')';
                                    
                                    if(showDetailType){
                                        name = name + ' [record pointer]';
                                    }

                                    arrterm.push([DT_PARENT_ENTITY, name, false]);    
                                    
                                    return false;
                            }
                        }
                    });
                    if(arrterm.length>0) break;
                }
            }

            
            let trash_id = $Db.getTrashGroupId('dtg');
                                        
            details.each2(function(dtyID, detail){
                if(dtyID){

                    let field_reqtype = detail['rst_RequirementType'];
                    
                    if(field_reqtype=='forbidden' || detail['dty_DetailTypeGroupID'] == trash_id) return true; 
                        //2021-02-19 || $Db.dty(dtyID, 'dty_ShowInLists')==0
                    
                    let field_type = $Db.dty(dtyID, 'dty_Type');

                    if(allowedlist==null || allowedlist.indexOf(field_type)>=0)
                    {

                        let name = detail['rst_DisplayName'];

                        if(addLatLongForGeo && field_type=="geo"){

                            arrterm.push([ dtyID, name + ' ['+$Db.baseFieldType[ field_type ]+']', (field_reqtype=="required") ]);
                            arrterm.push([ dtyID+'_long', name+' [X / Longitude]', false ]);
                            arrterm.push([ dtyID+'_lat', name+' [Y / Latitude]', false ]);

                            return;
                        }

                        if(showDetailType){
                            name = name + ' ['+$Db.baseFieldType[ field_type ]+']';
                        }

                        if(!window.hWin.HEURIST4.util.isnull(name)){
                            arrterm.push([ dtyID, name, (field_reqtype=="required") ]);    
                        }
                    }
                }
            });

            //sort by name
            arrterm.sort(function(a, b) {
                let nameA = a[1].toLocaleUpperCase(); // ignore upper and lowercase
                let nameB = b[1].toLocaleUpperCase(); // ignore upper and lowercase
                return nameA.localeCompare(nameB);
            });
            
            
            //add to select
            let i=0, cnt= arrterm.length;
            for(;i<cnt;i++) {
                let opt = window.hWin.HEURIST4.ui.addoption(selObj, arrterm[i][0], arrterm[i][1]);
                if(arrterm[i][2] && requriedHighlight){
                    opt.className = "required";
                }
                $(opt).attr('depth',initial_indent);
                if(useIds){
                    $(opt).attr('entity-id', arrterm[i][0]);
                }
            }

        }
        // for all fields
        else{ //show all detail types

            $Db.dtg().each2(function(groupID, group){
            
                let arrterm = [];
                
                $Db.dty().each2(function(detailID, field)
                {
                    if(field['dty_DetailTypeGroupID']==groupID &&
                      //2021-02-19 (field['dty_ShowInLists']==1) &&
                      (allowedlist==null || allowedlist.indexOf(field['dty_Type'])>=0))
                    {
                        arrterm.push([detailID, field['dty_Name']]);
                    }
                });


                if(arrterm.length>0){
                    
                    //add header    
                    if(useHtmlSelect){
                        let grp = document.createElement("optgroup");
                        grp.label = group['dtg_Name'];
                        selObj.appendChild(grp);
                    }else{
                        let opt = window.hWin.HEURIST4.ui.addoption(selObj, 0, group['dtg_Name']);
                        $(opt).attr('disabled', 'disabled');
                        $(opt).attr('group', 1);
                    }

                    //sort by name
                    arrterm.sort(function(a, b) {
                        const nameA = a[1].toLocaleUpperCase(); // ignore upper and lowercase
                        const nameB = b[1].toLocaleUpperCase(); // ignore upper and lowercase
                        return nameA.localeCompare(nameB);
                    });
                    
                    //add to select
                    let i=0, cnt= arrterm.length;
                    for(;i<cnt;i++) {
                        let opt = window.hWin.HEURIST4.ui.addoption(selObj, arrterm[i][0], arrterm[i][1]);
                        $(opt).attr('depth',1);
                        if(useIds){
                            $(opt).attr('entity-id', arrterm[i][0]);
                        }
                    }
                }
                
            });

        }

        
        
        if(options && options['bottom_options']){
            window.hWin.HEURIST4.ui.fillSelector(selObj, options['bottom_options']);
        }   

        if(selectedValue){
            $(selObj).val(selectedValue);
        }
        
        selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, useHtmlSelect, null, eventHandlers, extraOptions);

        return selObj;
    },

    /**
    *  get SELECT for list of given groups, othewise loads list of groups for current user    
    */
    createUserGroupsSelect: function(selObj, groups, topOptions, callback) {

        $(selObj).empty();

        if(groups=='all'){  //all groups - sorted by name
            
            groups = window.hWin.HAPI4.sysinfo.db_usergroups;
            
        }else 
        if(groups=='all_my_first'){ //all groups by name - my groups first
            
            if(!topOptions) topOptions = [];
            for (let groupID in window.hWin.HAPI4.currentUser.ugr_Groups)
            if(groupID>0){
                const name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                if(!window.hWin.HEURIST4.util.isnull(name)){
                        topOptions.push({key:groupID, title:name});
                }
            }
            topOptions.push({key:'', title:'──────────',disabled:true});
            
            groups = window.hWin.HAPI4.sysinfo.db_usergroups;
            
        }else 
        if(groups=='all_users' || groups=='all_users_and_groups' || groups == 'all_users_non_admins'){ //all users

            let all_users_ready = (groups=='all_users' || groups=='all_users_and_groups') && 
                window.hWin.HEURIST4.allUsersCache && window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HEURIST4.allUsersCache);
            
            let non_admins_ready = groups == 'all_users_non_admins' && 
                window.hWin.HEURIST4.allUsersNonAdmin && window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HEURIST4.allUsersNonAdmin);

            if(all_users_ready || non_admins_ready){
                if(topOptions){
                    topOptions = window.hWin.HEURIST4.util.cloneJSON(topOptions);
                    topOptions.push({key:'', title:'──────────',disabled:true});    
                } 
                if(groups=='all_users_and_groups'){
                    if(!topOptions) topOptions = [];
                    $.each(window.hWin.HAPI4.sysinfo.db_usergroups,function(idx,name){
                        topOptions.push({key:idx, title:name});
                    })
                    topOptions.push({key:'', title:'──────────',disabled:true});
                }
                groups = groups == 'all_users_non_admins' ? window.hWin.HEURIST4.allUsersNonAdmin : window.hWin.HEURIST4.allUsersCache;    
            }else{
            //It uses window.hWin.HEURIST4.allUsersCache
    
                //get all users
                let request = {a:'search', entity:'sysUsers', details:'fullname', 'sort:ugr_LastName': '1'};

                if(groups == 'all_users_non_admins'){
                    request['members_only'] = '1';
                    request['not:ugl_GroupID'] = '1';
                }
                //Note: it searches for all users - including disabled
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        let recordset = new HRecordSet(response.data);
                        groups == 'all_users_non_admins' ? window.hWin.HEURIST4.allUsersNonAdmin = [] 
                                                         : window.hWin.HEURIST4.allUsersCache = [];
                        if(recordset.count_total()>0){
                            recordset.each2(function(id,rec){
                                let record = {id: id, name: rec['ugr_FullName']};
                                groups == 'all_users_non_admins' ? window.hWin.HEURIST4.allUsersNonAdmin.push(record) 
                                                                 : window.hWin.HEURIST4.allUsersCache.push(record);
                            });
                            window.hWin.HEURIST4.ui.createUserGroupsSelect(selObj, groups, topOptions, callback);    
                        }else if(typeof callback === "function"){
                            callback(false);
                        }

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
                return;
            }

        }else
        if(!groups){ //not defined - use groups of current user
        
            groups = {};
            for (let groupID in window.hWin.HAPI4.currentUser.ugr_Groups)
            if(groupID>0){
                const name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                if(!window.hWin.HEURIST4.util.isnull(name)){
                        groups[groupID] = name;
                }
            }
        }

        let addedontop = [];
        if(topOptions){  //list of options that must be on top of list
            for (let idx in topOptions)
            {
                if(idx){
                    const key = topOptions[idx].key;
                    const title = topOptions[idx].title;
                    if(!window.hWin.HEURIST4.util.isnull(title))
                    {
                        window.hWin.HEURIST4.ui.addoption(selObj, key, title, (topOptions[idx].disabled==true));
                        addedontop.push(key);
                    }
                }
            }
        }
        if(groups){   //it may 1) array of group ids 2) array of objects 3) [ids=>name] 4) [ids=>array(0,name,0)]

            for (let idx in groups)
            {
                if(idx>=0){
                    
                    let groupID = groups[idx];
                    let name = null;
                    if(parseInt(groupID)>0){ //case 1
                        name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                    }else if(window.hWin.HEURIST4.util.isObject(groupID) && Object.hasOwn(groupID, 'id')){ //case 2
                        name = groupID['name'];
                        groupID = groupID['id'];
                    }else{ //case 3
                        groupID = idx;
                        name = groups[groupID];    
                        if(Array.isArray(name)) name = name[1] //backward  case 4
                    }
                    
                    if(window.hWin.HEURIST4.util.findArrayIndex(groupID,addedontop)<0 
                        && !window.hWin.HEURIST4.util.isnull(name))
                    {
                        window.hWin.HEURIST4.ui.addoption(selObj, groupID, name);
                    }
                }
            }
        }

        if(typeof callback === "function"){
            callback(true);
        }
    },
    
    //
    // eventHandlers {object} - object of event handlers ('onSelectMenu', 'onOpenMenu', 'onCloseMenu')
    //  onSelectMenu - in case defined use this callback instead of trigger
    //  onOpenMenu - called after selectmenu menuWidget has been created and rendered
    //  onCloseMenu - called after selectmenu loses focus and menuWidget is hidden
    //
    // extraOptions {object} - object to handle extra settings/setup
    //  menu_parent - move the menuWidget into a different element (usually to avoid the menu from not appearing)
    //
    initHSelect: function(selObj, useHtmlSelect, apply_style, eventHandlers, extraOptions){            

        selObj = $(selObj);

        if(!extraOptions || !$.isPlainObject(extraOptions)){
            extraOptions = {};
        }
            
        //for usual HTML select we have to add spaces for indent
        if(useHtmlSelect){
            
            selObj.find('option').each(
                function(idx, item){
                    let opt = $(item);
                    let depth = parseInt(opt.attr('depth'));
                    if(depth>0) { 
                        //for non mozilla add manual indent
                        let a = new Array( 2 + ((depth<7)?depth:7)*2 );
                        opt.html(a.join('&nbsp;&nbsp;') + opt.text());       
                    }
            });
            
        }else{
            
            let parent_ele = selObj.parents('.selectmenu-parent');
            if(!parent_ele || parent_ele.length==0){
                
                if ( selObj[0].ownerDocument.location !== window.location ){ //inside iframe
                    let win = selObj[0].ownerDocument.defaultView || selObj[0].ownerDocument.parentWindow;
                    parent_ele = $(win.frameElement).parents('.ui-dialog');
                    if(parent_ele.length==0){
                       
                        parent_ele = $(selObj[0].ownerDocument.body);
                    }else{
                        selObj.addClass('adjust-to-button');
                    }
                }
            
                if(!parent_ele || parent_ele.length==0){
                    parent_ele = selObj.parents('.ui-dialog');
                    if(!parent_ele || parent_ele.length==0) {

                        parent_ele = selObj.parents('.selectmenu-parent'); //add special class to some top most div 

                        /*
                        var sel = $(selObj)[0];
                        if(sel.ownerDocument != document){ //inside iframe

                        var pwin = (sel.ownerDocument.parentWindow || sel.ownerDocument.defaultView);
                        var parent_dlg_id = pwin.frameElement.getAttribute("parent-dlg-id");
                        parent_ele = $('#'+parent_dlg_id).parent();    
                        }                
                        //parent dialog not found
                        if(parent_ele.length==0)
                        */
                        if(!parent_ele || parent_ele.length==0) {
                            //among parent not found - global search
                            parent_ele = $('.selectmenu-parent');
                            if(!parent_ele || parent_ele.length==0) {                
                                parent_ele = selObj.parent();   
                            }
                        }
                    }        
                }
            }
            
            if(selObj.hSelect("instance")!=undefined){
                selObj.hSelect("destroy"); 
            }
            
            let onSelectMenu, onOpenMenu, onCloseMenu;
            if(eventHandlers && $.isPlainObject(eventHandlers)){
                if(window.hWin.HEURIST4.util.isFunction(eventHandlers.onOpenMenu)){
                    onOpenMenu = eventHandlers.onOpenMenu;
                }
                if(window.hWin.HEURIST4.util.isFunction(eventHandlers.onCloseMenu)){
                    onCloseMenu = eventHandlers.onCloseMenu;
                }
                if(window.hWin.HEURIST4.util.isFunction(eventHandlers.onSelectMenu)){
                    onSelectMenu = eventHandlers.onSelectMenu;
                }
            }else if(eventHandlers && window.hWin.HEURIST4.util.isFunction(eventHandlers)){
                onOpenMenu = eventHandlers;
            }
             
            let menu = selObj.hSelect({ 
                style: 'dropdown',
                position: {collision: "flip"},  //(navigator.userAgent.indexOf('Firefox')<0)?{collision: "flip"}:{},
                appendTo: parent_ele,
                change: function( event, data ) {
 
                        selObj.val((data && data.item)?data.item.value:data);//change value for underlaying html select

                        if(onSelectMenu && window.hWin.HEURIST4.util.isFunction(onSelectMenu)){
                            onSelectMenu.call(this, event);
                        }else{
                            selObj.trigger('change');
                        }
                },
                open: function(event, ui){
                    //increase width of dropdown to avoid word wrap
                    let wmenu = $(event.target).hSelect( "menuWidget" );  //was menu
                    wmenu.width( wmenu.width()+20 ); 
                    let wmenu_div = wmenu.parent('div.ui-selectmenu-menu');
                    let pos = wmenu_div.position().top;

                    let bodyheight = (window.hWin?window.hWin.innerHeight:window.innerHeight);
                    
                    if(bodyheight>0 && pos+wmenu.height()>bodyheight){
                        let newtop = bodyheight-wmenu.height()-5;
                        if(newtop<0){
                            newtop = 2;
                            wmenu_div.height(bodyheight-2);
                        }
                        wmenu_div.css('top', newtop);
                    }
                    
                    if ( selObj[0].ownerDocument.location !== window.location ){
                        let wbtn = $(event.target).hSelect('widget');
                        if($(event.target).hasClass('adjust-to-button')){
                            wmenu_div.css('left', wbtn.position().left);      
                        }
                    }
                    
                    wmenu_div.css('zIndex',69999);

                    if(onOpenMenu && window.hWin.HEURIST4.util.isFunction(onOpenMenu)){
                        onOpenMenu.call(this, event);
                    }
                },
                close: function(event, ui){
                    if(onCloseMenu && window.hWin.HEURIST4.util.isFunction(onCloseMenu)){
                        onCloseMenu.call(this, event);
                    }
                }
            });

            let dwidth = selObj.css('width');    
            if(!dwidth || dwidth=='0px' || (dwidth.indexOf('px')>0 && parseFloat(dwidth)<21)) dwidth = 'auto';
            
            let dminwidth = selObj.css('min-width');    
            if(dminwidth=='0px' || window.hWin.HEURIST4.util.isempty(dminwidth)) dminwidth = '10em';

            let menuwidget = menu.hSelect( "menuWidget" );
            menuwidget.css( {'background':'#F4F2F4'}); //'padding':0,
            menuwidget.addClass('heurist-selectmenu overflow').css({'max-height':'280px','font-size':'12px'});
            menuwidget.parent('div.ui-selectmenu-menu').css('zIndex',69999);
            
            //menuwidget.find()
            
            if(apply_style){
                menu.hSelect( "widget" ).css(apply_style);    
                menu.hSelect( "menuWidget" ).css(apply_style);
            }else{
                menu.hSelect( "widget" ).css({'padding':0, 'font-size':'1.1em', //'background':'#FFF',
                    width:(dwidth?dwidth:'auto'),'min-width':dminwidth }); //,'min-width':'16em''#F4F2F4'
            }

            if(extraOptions.menu_parent && extraOptions.menu_parent.length > 0){
                menuwidget.parent().appendTo(extraOptions.menu_parent);
            }
        }
        return selObj;
    },           
    
    //
    // exp_level 2 beginner, 1 intermediate, 0 expert
    // heurist-helper2  for level>1
    // heurist-helper1  for level>0
    //
    applyCompetencyLevel: function(exp_level, $context){

            if(!(exp_level>=0)){
                exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2); //beginner default
            }
            
            if(!$context){
                $context = $(window.hWin.document);
            }
            
            $context.find('li[data-user-experience-level]').each(function(){
                if(exp_level > $(this).data('exp-level')){
                    $(this).hide();    
                }else{
                    $(this).show();    
                }
            });
            
            
            if($context.hasClass('manageRecords')){
                //special bhaviour for record edit form
                let prefs = window.hWin.HAPI4.get_prefs_def('prefs_records');
                if(prefs){
                    let ishelp_on = (prefs['help_on']==true || prefs['help_on']=='true');
                    window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $context);
                }
            }
            
            $context.trigger('competency', exp_level); //some contexts need specific behaviour to apply the level
            
    }, 
      
    // Init button that show/hide help tips for popup dialog
    //
    //  usrPrefKey - prefs_entityName - context 
    //
    initDialogHintButtons: function($dialog, button_container_id, helpcontent_url, hideExpLevelButton){
        
        //IJ 2018-12-04 hide it! 
        let hasContextHelp =  !window.hWin.HEURIST4.util.isempty(helpcontent_url);
        hideExpLevelButton = true; //always hide
        
        let titlebar = $dialog.parent().find('.ui-dialog-titlebar');
        if(titlebar.length==0){
            titlebar = $dialog.find(button_container_id);
        } 
        
        if(!hideExpLevelButton){
            let $help_menu = $('<ul><li data-user-admin-status="2"><a><span class="ui-icon"></span>Beginner</a></li>'
                +'<li data-user-admin-status="1"><a><span class="ui-icon"></span>Intermediate</a></li>'
                +'<li data-user-admin-status="0"><a><span class="ui-icon"></span>Expert</a></li><ul>')
                .width(150).hide().appendTo($dialog);
            
        let $help_button = $('<div>').button({icon: "ui-icon-book" , 
                    label:'Set experience level for user interface', showLabel:false})
                    .addClass('dialog-title-button')
                    .css({'right':hasContextHelp?'48px':'26px'})
                    .appendTo(titlebar)
                    .on('click', function(event){
                           //show popup menu 
                           let exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
                           
                           $help_menu.find('span').removeClass('ui-icon-check');
                           $help_menu.find('li[data-user-admin-status="'+exp_level+'"] > a > span').addClass('ui-icon-check');
                           
                           
                           if($help_menu.parent().length==0){
                               $help_menu.menu().appendTo($help_button.parents('.ui-dialog').find('.ui-dialog-content'));
                           }
                           $help_menu.menu().on( {
                               //mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
                               click: function(event){ 
                                   //change level
                                   let exp_level = $(event.target).parents('li').attr('data-user-admin-status');

                                   window.hWin.HAPI4.save_pref('userCompetencyLevel', exp_level);

                                   window.hWin.HEURIST4.ui.applyCompetencyLevel(exp_level, $dialog);

                                   $help_menu.find('span').removeClass('ui-icon-check');
                                   $help_menu.find('li[data-user-admin-status="'+exp_level+'"] > a > span').addClass('ui-icon-check');
                                   $help_menu.hide();
                               },
                               mouseleave : function(){ $help_menu.hide()}
                           });
            
                           
                           $help_menu.show().css('z-index',9999999)
                            .position({my: "right top+10", at: "right bottom", of: $help_button });
                            
                           //window.hWin.HEURIST4.ui.applyCompetencyLevel(exp_level, $dialog); 
                    });


           //window.hWin.HEURIST4.ui.switchHintState(usrPrefKey, $dialog, false);         
        }

        if(hasContextHelp){                    
            let $info_button = $('<div>')
                    .addClass('dialog-title-button')
                    .css({'right':'26px'})
                    .appendTo(titlebar);
                    
            window.hWin.HEURIST4.ui.initHelper({button:$info_button, url:helpcontent_url});
        }
                    
    },
      
    //
    // not used anymore
    //                  
    switchHintState: function(usrPrefKey, $dialog, needReverse){
            
            let ishelp_on, prefs;
            if(usrPrefKey==null){
                ishelp_on = window.hWin.HAPI4.get_prefs('help_on');   
            }else{
                prefs = window.hWin.HAPI4.get_prefs(usrPrefKey);   
                ishelp_on = prefs ?prefs.help_on:true;
            }

            //change to reverse
            ishelp_on = (ishelp_on==1 || ishelp_on==true || ishelp_on=='true');
            if(needReverse){
                ishelp_on = !ishelp_on;
                if(usrPrefKey==null){
                    window.hWin.HAPI4.save_pref('help_on',ishelp_on);
                }else{
                    if(!prefs) prefs = {};
                    prefs.help_on = ishelp_on;
                    window.hWin.HAPI4.save_pref(usrPrefKey, prefs);
                }
            }
            
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, $dialog);
            
    },
    
    //
    //  used in manageRecords only
    //
    switchHintState2: function(state, $container, className){
        
            if(!className){
                className = '.heurist-helper1';
            }
        
            if(state){
                //$help_button.addClass('ui-state-focus');    
                $.each($container.find(className),function(i,ele){ 
                    if(!$(ele).hasClass('separator-hidden')) $(ele).css('display','block');
                });
                $.each($container.find('div.div-table-cell'+className),function(i,ele){ 
                    if(!$(ele).hasClass('separator-hidden')) $(ele).css('display','table-cell');
                });
                
               
               
            }else{
               
                $container.find('div.div-table-cell'+className).css('display','none');
                $container.find(className).css('display','none');
            }
    },
    
    //
    // Inits helper div (slider) and button
    //  options:  
    //      button - ref to element
    //      no_init - do not init button
    //      title - header of popup
    //      url - ref to help file
    //      position - juquery position - default to bottom of button
    //      container - adds help to this container rather than popup
    //      is_open_at_once   
    //
    initHelper: function( options ){
        
        
        let $help_button = $(options.button);

        if(options.no_init!==true){ //do not init button    ui-icon-circle-b-info  carat-2-e
            $help_button.button({icon:"ui-icon-circle-help", label:window.hWin.HR('Show context help'), showLabel:false});
        }
        
        let is_popup = false;
        if(!options.container){
            is_popup = true;
            options.container = $(document.body);
        }
        let oEffect = {effect:'slide',direction:'right',duration:500};
        
        function __closeHelpDiv($helper_div){
            oEffect.complete =  function(){ options.container.children('.ent_content_full').css({right:1, width:'auto'}); };
            $helper_div.hide(oEffect);
            $help_button.button({icon:"ui-icon-circle-help"});
        }
        
        $help_button.on('click', function(event){
            
                        let $helper_div = options.container.find('.ui-helper-popup');
                        
                        if($helper_div.length==0){
                            $helper_div = $('<div>').addClass('ui-helper-popup')
                                            .hide().appendTo(options.container);
                            
                            if(is_popup){
                                
                                $helper_div.dialog({
                                            autoOpen: false, 
                                            //title: window.hWin.HR(options.title),
                                            show: oEffect,
                                            hide: oEffect
                                         });                 
                            }else{
                                $helper_div.addClass('ui-helper-slideout');
                                $helper_div.css('width','30%');
                                /*
                                _innerTitle = $('<div>').addClass('ui-heurist-header').appendTo($helper_div);  
                                
                                $('<span>').appendTo(_innerTitle);
                                $('<button>').button({icon:'ui-icon-closethick',showLabel:false, title:'Close'}) 
                                                        //classes:'ui-corner-all ui-dialog-titlebar-close'})
                                         .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                                         .appendTo(_innerTitle)
                                         .on({click:function(){
                                             $helper_div.hide(oEffect);
                                            
                                         }});
                                
                                
                                $('<div>').css({top:38}).addClass('ent_wrapper').appendTo($helper_div);  
                                */
                            }
                        }
                        
                        if(is_popup){
                            
                            if($helper_div.dialog('instance') && $helper_div.dialog( 'isOpen' )){
                                $helper_div.dialog( "close" );
                            }else{                        
                            
                                let divpos = null;
                                if(options.position && $.isPlainObject(options.position)){
                                    divpos = options.position;
                                   
                                }else if(options.position=='top'){ //show div above button
                                    divpos = { my: "right bottom", at: "right top", of: $help_button }
                                }else{
                                    divpos = { my: "right top", at: "right bottom", of: $help_button };
                                }
                                
                                $helper_div.load(options.url, function(response, status, xhr){
                                    
                                    if(status=='error'){
                                        
                                        window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Sorry context help was not found'),1000);
                                        
                                    }else{

                                        let div_width  = Math.min(700, $(document.body).width() *0.8);
                                       
                                        let head = $helper_div.find('#content>h2');
                                        if(head.length==1){
                                            if(!options.title) options.title = head.text();
                                            head.empty();
                                        }
                                        if(!options.title){
                                            options.title = window.hWin.HR('Heurist context help');
                                        }
                                    
                                        $helper_div.dialog('option','title', options.title);
                                        $helper_div.dialog('option', {width:div_width, height: 'auto', position: divpos});
                                        $helper_div.dialog( "open" );
                                        setTimeout(function(){
                                                $helper_div.find('#content').scrollTop(1);
                                        }, 1000);
                                    
                                        //click outside    
                                        $( document ).one( "click", function() { $helper_div.dialog( "close" ); });
                                    }
                                });
                            }
                            
                            
                        }else{
                            
                            if($helper_div.is(':visible')){
                                __closeHelpDiv($helper_div);         
                            }else{
                                $help_button.button({icon:"ui-icon-carat-2-e"});
                                //find('.ent_wrapper')
                                $helper_div.load(options.url, function(response, status, xhr){

                                    if(status=='error'){
                                        
                                        window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Sorry context help was not found'));
                                        __closeHelpDiv($helper_div);
                                        
                                    }else{

                                        /*
                                        $helper_div.css({position:'absolute',right:1,top:38,bottom:1,
                                                    width:300,'font-size':'0.9em',padding:'4px'});
                                        */
                                        
                                        let head = $helper_div.find('#content>h2');
                                        if(head.length==1){
                                            if(!options.title) options.title = head.text();
                                            head.empty();
                                        }
                                        if(!options.title){
                                            options.title = window.hWin.HR('Heurist context help');                                    
                                        }
                                        
                                       
                                        $helper_div.show( 'slide', {direction:'right'}, 500 );                        
                                        options.container.children('.ent_content_full').css({width:'70%'}); //right:424
                                    
                                        setTimeout(function(){
                                                $helper_div.find('#content').scrollTop(1);
                                        }, 1000);
                                    }

                                
                                });
                            }
                            
                        } 
                        
                        
                 });
                 
        if(options.is_open_at_once && options.container){
            options.container.find('.ui-helper-popup').hide();
            $help_button.trigger('click');    
        }
    },
    
    //
    // show login popup dialog 
    //
    checkAndLogin: function(isforsed, callback){

        if(!window.hWin.HAPI4.has_access()){
            // {status:window.hWin.ResponseStatus.REQUEST_DENIED} 
            if(typeof showLoginDialog !== 'undefined' && window.hWin.HEURIST4.util.isFunction(showLoginDialog)){  // already loaded in index.php
                
                showLoginDialog(isforsed, callback);
            }else{
                $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_login.js', function(){
                    window.hWin.HEURIST4.ui.checkAndLogin(isforsed, callback);
                }); 
            }
            return false;
        }else{
            return true;
        }
        
    },

    //
    // important manageRecords.js and selectRecords.js must be loaded
    // 
    // rec_ID - record to edit
    // query_or_recordset - recordset or query returns set of records that can be edit in bunch (next/prev buttons)
    // popup_options['onselect'] - define function that accepts added/edited record as recordset
    // popup_options['edit_structure'] - allows edit structure only
    // 
    openRecordEdit:function(rec_ID, query_or_recordset, popup_options){

        if(!window.hWin.HEURIST4.ui.checkAndLogin(false, function(is_logged){
                    if(is_logged!==false){
                        window.hWin.HEURIST4.ui.openRecordEdit(rec_ID, query_or_recordset, popup_options);
                    }
                })){
            return;
        }
        
        if(popup_options && 
            $.isPlainObject(popup_options.new_record_params) && 
                (popup_options.new_record_params['rt']>0 || popup_options.new_record_params['RecTypeID']>0)){
           
            query_or_recordset = null;
            
        }
        
        if(popup_options && popup_options.edit_structure){
            //check that select record type is opened - resize and position this popup
            let ele = $('body').find('div[id^="heurist-dialog-DefRecTypes"]');
            if(ele.length>0){
                popup_options.forced_Width = (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.6;
                popup_options.position = {my: "left top", at: "left top", of: ele };
            }

        }
        
        
        popup_options = $.extend(popup_options, {
            select_mode: 'manager',
            edit_mode: 'editonly', //only edit form is visible, list is hidden
            //height: usrPreferences.height,
            //width: usrPreferences.width,
            select_return_mode:'recordset',
            
            title: window.hWin.HR('Edit record'),
            layout_mode:'<div class="ent_wrapper editor">'
                + '<div class="ent_content_full recordList"  style="display:none;"></div>'

                //+ '<div class="ent_header editHeader"></div>'
                + '<div class="editFormDialog ent_content_full">'
                
                        + '<div class="ui-layout-north">'
                                +'<div class="editStructureHeader" style="background:white"></div>'
                        + '</div>' 
                
                        + '<div class="ui-layout-west"><div class="editStructure treeview_with_header" style="background:white">'
                            +'</div></div>' //container for rts_editor
                        + '<div class="ui-layout-center"><div class="editForm"></div></div>'
                        + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                        //+ '<div class="ui-layout-south><div class="editForm-toolbar"></div></div>'
                + '</div>'
                //+ '<div class="ent_footer editForm-toolbar"></div>'
            +'</div>',
            onInitFinished:function( last_attempt ){
                
                if( window.hWin.HEURIST4.util.isRecordSet(query_or_recordset) )
                {
                    //array of record ids 
                    this.updateRecordList(null, {recordset:query_or_recordset});
                    this.addEditRecord( (rec_ID>0)?rec_ID:query_or_recordset.getOrder()[0] );
                    return;
                }
                
                let query_request = null;
                
                if(query_or_recordset){
                    if(!$.isPlainObject(query_or_recordset)){ //just string
                        query_request = {q:query_or_recordset, w:'all'};
                    }
                }else if(query_request==null && rec_ID>0){
                    query_request = {q:'ids:'+rec_ID, w:'e'}; //including temporary
                }
                
                let widget = this; //reference to manageRecords
                
                //find record or add after complete of initialiation of popup
                if(query_request){
                    
                    query_request['limit'] = 100;
                    query_request['needall'] = 1;
                    query_request['detail'] = 'ids';
                
                    window.hWin.HAPI4.RecordMgr.search(query_request, 
                    function( response ){
                       
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            let recset = new HRecordSet(response.data);
                            if(recset.length()>0){

                                widget.updateRecordList(null, {recordset:recset});
                                widget.addEditRecord( (rec_ID>0)?rec_ID:recset.getOrder()[0] );
                            }
                            else {
                                if(last_attempt!==true && query_request && rec_ID>0){
                                    query_request = null;
                                    widget.options.onInitFinished.call(widget, true);
                                    return;
                                }
                                
                                const sMsg = `Record${rec_ID > 0 ? ' id #'+rec_ID : ''} does not exist in this database or it has been hidden for non-owners`;

                                window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, 
                                    {ok:'Close', title:'Record not found or hidden'}, 
                                    {close:function(){ widget.closeEditDialog();}, default_palette_class: 'ui-heurist-populate'}
                                );
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            widget.closeEditDialog();
                        }

                    });
                }else{
                    widget.addEditRecord(-1);
                }
            },
            //selectOnSave: window.hWin.HEURIST4.util.isFunction(callback),
            //onselect: callback
        });

        window.hWin.HEURIST4.ui.showEntityDialog('Records', popup_options);
    },
    
    //
    //  Opens record edit  ->  openRecordEdit 
    //            or viewer  -> renderRecordData.php
    //  query_request - recordset or query string 
    //
    openRecordInPopup:function(rec_ID, query_request, isEdit, popup_options){
    
            let url = window.hWin.HAPI4.baseURL,
                dwidth, dheight, dtitle;    
            
            if(!popup_options) popup_options = {};
                
            if(isEdit==true){
                window.hWin.HEURIST4.ui.openRecordEdit(rec_ID, query_request, popup_options);
                return;
                
                // section below NOT USED
                // it loads manageRecords in popup iframe
                /*  
                var usrPreferences = window.hWin.HAPI4.get_prefs_def('edit_record_dialog', 
                        {width: (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

                url = url + 'hclient/framecontent/recordEdit.php?popup=1&';
                
                if(query_request){
                    if($.isPlainObject(query_request)){
                        url = url + window.hWin.HEURIST4.query.composeHeuristQueryFromRequest(query_request, true);
                    }else{
                        url = url + 'db=' + window.hWin.HAPI4.database + '&q=' + encodeURIComponent(query_request);                              }
                }else{
                    url = url + 'db=' + window.hWin.HAPI4.database;
                }
                
                url = url + '&recID='+rec_ID;
                    
                dtitle = 'Edit record';
                dheight = usrPreferences.height;
                dwidth = usrPreferences.width;
                */
            }else{
                url = url + 'viewers/record/renderRecordData.php?db='+window.hWin.HAPI4.database
                +'&recID='+ rec_ID;
                                                        
                dtitle = 'Record Info';
                dheight = 640;
                dwidth = 800;
            
                let $dosframe = window.hWin.HEURIST4.msg.showDialog(url, {
                    height:dheight, 
                    width:dwidth,
                    padding:0,
                    title: window.hWin.HR(dtitle),
                    class:'ui-heurist-bg-light',
                    callback: popup_options.callback,
                    beforeClose: function(){
                        //access manageRecord within frame within this popup and call close prefs
                        if($dosframe && window.hWin.HEURIST4.util.isFunction($dosframe[0].contentWindow.onBeforeClose)){
                                $dosframe[0].contentWindow.onBeforeClose();
                        }
                    }
                    });
            }        
    },
    
    //
    // info {rec_ID,rec_Title,rec_RecTypeID,relation_recID,trm_ID,dtl_StartDate,dtl_EndDate,rec_IsChildRecord}
    //
    //
    // selector_function opens select dialog. it it is true it opens record edit popup dialog
    createRecordLinkInfo:function(container, info, selector_function){
        
        //headers[targetID][0], headers[targetID][2] + headers[targetID][3]
       
        let rec_Title = info['rec_Title'];
        if(info['dtl_StartDate'] || info['dtl_EndDate']){
            rec_Title += ': ';
            if(info['dtl_StartDate']){
                rec_Title += info['dtl_StartDate'];
            }
            if(info['dtl_EndDate']){
                rec_Title += (' - '+info['dtl_EndDate']);
            }
        }
        rec_Title = window.hWin.HEURIST4.util.stripTags(rec_Title); //was htmlEscape
        
        let ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
        let sRelBtn = '';
       
        let isHiddenRecord = false;
        
        if(selector_function !== false ){
                
            
                let not_owner = !(window.hWin.HAPI4.is_admin() || window.hWin.HAPI4.is_member( info['rec_OwnerUGrpID'] ));
                if(not_owner){
                     selector_function = false;
                     //this record hidden for current user
                     isHiddenRecord = (info['rec_NonOwnerVisibility']=='hidden');
                }
        }
        
        let isEdit = (selector_function!==false);
        
        if(info['trm_ID']>0){
           
            sRelBtn = 
                '<div style="display:table-cell;min-width:120px;text-align:right;vertical-align: middle;">'
                +'<div class="btn-edit"></div><div class="btn-rel"></div><div class="btn-del"></div></div>';
        }else if (!isHiddenRecord) {
            sRelBtn = '<div style="display:table-cell;min-width:23px;text-align:right;padding-left:16px;vertical-align: middle;">'
            +'<div class="btn-edit"></div></div>';     // data-recID="'+info['rec_ID']+'"
        }
        
        let reltype = ''
        if(info['trm_ID']>0){
            let term_ID = info['trm_ID'];
            if (info['is_inward']){
                term_ID = window.hWin.HEURIST4.dbs.getInverseTermById(term_ID);    
            }
            let lbl = $Db.trm(term_ID, 'trm_Label');
            let len = lbl.length;
            lbl = window.hWin.HEURIST4.util.htmlEscape(lbl);
           
            reltype = '<div style="display:table-cell;min-width:'+(Math.min(20,len)+1)+'ex;'
                +'color: #999999;text-transform: none;padding-left:4px;vertical-align: middle;">'
                +  lbl + '</div>'
        }
        
        let rectype_icon = '<div style="display:table-cell;vertical-align: middle;padding: 0 4px'+(reltype==''?'':' 0 16px')+';">'
                        + '<img alt src="'+ph_gif+'"  class="rt-icon" style="' //'margin-right:10px;'
                        + ((info['rec_RecTypeID']>0)?
                            'background-image:url(\''   
                            + top.HAPI4.iconBaseURL+info['rec_RecTypeID'] + '\');'   //rectype icon
                           :'') 
                        + '"/>'
                        + '</div>';
        
        let ele = $('<div class="link-div ui-widget-content ui-corner-all"  data-relID="'
                        +(info['relation_recID']>0?info['relation_recID']:'')+'" '
                        +' style="display: table-row;margin-bottom:0.2em;'
                        +(isEdit?'background:#F4F2F4 !important;':'')+'">'

                        //relation type
                        
        + '<div '  // class="detail"   truncate
                        + 'style="display:table-cell; word-break: break-word; vertical-align: middle;">' 
                        
                        + reltype
                        
                        + (info['rec_IsChildRecord']==1
                            ?'<span style="font-size:0.8em;color:#999999;padding:4px 2px;display:table-cell;min-width: 5ex;vertical-align:middle;">'
                                +'child</span>':'')
                            
                        //triangle icon fo
                        + ((reltype!='')?'<span style="display:table-cell;vertical-align:middle;padding-top:3px">'
                            +'<span class="ui-icon ui-icon-triangle-1-e"></span></span>':'')

                        //record type icon for resource
                        + (reltype==''?rectype_icon:'')
                        
                        // record title 
                        
                        +'<span class="related_record_title'
                            + ((info['rec_RecTypeID']>0)?'':' ui-state-error')
                            + '" data-rectypeid="'+ info['rec_RecTypeID']
                            + '" data-recid="'
                                        +info['rec_ID']
                                        +'" style="display:table-cell;vertical-align:middle">' 
                        + rec_Title
                        + '</span>'
                        
        + '</div>'
                        //record type icon for relmarker
                        //+ (reltype==''?'': '<div class="btn-edit"></div>')                        
                        + sRelBtn
                        + '</div>')
        .appendTo($(container));
        
        
        if(isEdit){
            
            if(window.hWin.HEURIST4.util.isFunction(selector_function)){
                let triangle_icon = ele.find('.ui-icon-triangle-1-e');
                if(triangle_icon.length>0){
                   ele.find('.detail').css({'cursor':'hand'});
                   triangle_icon.on('click',selector_function);
                }
                ele.find('span[data-recID='+info['rec_ID']+']').on('click',selector_function);
            }
            
            //remove button
            ele.find('.btn-del').button({showLabel:false, label:top.HR('Remove '+(info['relation_recID']>0?'relation':'link')),
                            icon:'ui-icon-circlesmall-close'})
            .css({'font-size': '0.8em', height: '21px', 'max-width': '18px'})
            .on('click', function(event){
                window.hWin.HEURIST4.msg.showMsgDlg(
                    'You are about to delete link between records<br><br>Are you sure?',
                    function(){

                        let recID = ele.attr('data-relID');

                        if(recID>0){

                            window.hWin.HAPI4.RecordMgr.remove({ids: recID},
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){

                                    ele.trigger('remove');
                                    ele.remove();
                                    window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Relation has been deleted'), 3000);

                                    if($(container).find('.link-div').length==0){
                                        $(container).find('.add-rel-button').show();
                                    }

                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                            });

                        }else{
                            // @todo - remove link field
                        }
                    },
                    {title:'Warning',yes:'Proceed',no:'Cancel'}
                );
            });
        }
        

        if(info['relation_recID']>0){
            
            let bele = ele.find('.btn-rel');
            
            $('<img alt src="'+ph_gif+'"  class="rt-icon" style="vertical-align: middle;background-image:url(\''
                            + top.HAPI4.iconBaseURL + '1\');"/>'
            +'<span class="ui-button-icon ui-icon ui-icon-pencil" style="margin:0"></span>').appendTo(bele);
            
            //.button({showLabel:false, label:top.HR((isEdit?'Edit':'View')+' relationship record'),icon:'ui-icon-pencil'})
            
            bele.addClass('ui-button').css({'font-size': '0.8em', height: '18px', 'max-width': '40px',
                'min-width': '40px', display: 'inline-block', padding: 0, background: 'none'})
            .on('click', function(event){
                event.preventDefault();
                
                let recID = ele.attr('data-relID');
                window.hWin.HEURIST4.ui.openRecordInPopup(recID, null, isEdit,
                    {relmarker_field: info['relmarker_field'], relmarker_is_inward: info['is_inward'],
                    selectOnSave:true, edit_obstacle: true, onselect:
                    function(event, res){
                        if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                            const is_inward = info['is_inward'];
                            let recordset = res.selection;
                            let record = recordset.getFirstRecord();
                            let related_ID = recordset.fld(record, 'rec_ID'); //relationship record                             
                            const DT_RELATION_TYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'];
                            const DT_RELATED_REC_ID = window.hWin.HAPI4.sysinfo['dbconst'][is_inward?'DT_PRIMARY_RESOURCE':'DT_TARGET_RESOURCE'];

                            // e - search for temp also
                            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+related_ID, w: "e", 
                                        f:[DT_RELATION_TYPE,DT_RELATED_REC_ID]},  
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    let recordset = new HRecordSet(response.data);
                                    if(recordset.length()>0){
                                        let record = recordset.getFirstRecord();
                                        /*
                                        let term_ID = recordset.fld(record,DT_RELATION_TYPE);
                                        //update relation type !!!!
                                        if(info['is_inward']){
                                            term_ID = window.hWin.HEURIST4.dbs.getInverseTermById(term_ID);
                                        }*/
                                        ele.find('.detailType').text($Db.trm(info['trm_ID'], 'trm_Label')); 
                                        let related_ID = recordset.fld(record, DT_RELATED_REC_ID);  

                                        // e - search for temp also
                                        window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+related_ID, w: "e", f:"header"},  
                                        function(response){
                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                let recordset = new HRecordSet(response.data);
                                                if(recordset.length()>0){
                                                    let record = recordset.getFirstRecord();
                                                    let rec_Title = recordset.fld(record,'rec_Title');
                                                    if(!rec_Title) {rec_Title = 'New record. Title is not defined yet.';}
                                        
                                                    ele.find('.related_record_title')
                                                            .text( window.hWin.HEURIST4.util.stripTags(rec_Title) )
                                                            .attr('data-recid', related_ID);
                                                            
                                                }
                                            }
                                        });
                                    }
                                }
                            });
                            
                        }
                }});
            });
        
        }
        
        /* 2017-08-11 no more link for edit linked record :(    
        ele.find('a').on('click', function(event){
            event.preventDefault();
            var inpt = $(event.target);
            var recID = inpt.attr('data-recID');
            window.hWin.HEURIST4.ui.openRecordInPopup(recID, null, isEdit,
            {selectOnSave:true, edit_obstacle: true, onselect: 
                    function(event, res){
                        if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                            var recordset = res.selection;
                            var record = recordset.getFirstRecord();
                            var rec_Title = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record,'rec_Title'));
                            inpt.text(rec_Title);
                        }
                    }}
                );
        });        
        */
        let btn_edit = ele.find('div.btn-edit');
        if(btn_edit.length>0){
            
            
            //rectype_icon+
            
            if(info['rec_RecTypeID']>0){
                
                
                if(info['relation_recID']>0){
            
                    $('<img alt src="'+ph_gif+'"  class="rt-icon" style="vertical-align: middle;background-image:url(\''
                            + top.HAPI4.iconBaseURL + info['rec_RecTypeID'] + '\');"/>'
                            +'<span class="ui-button-icon ui-icon ui-icon-pencil" style="margin:0"></span>').appendTo(btn_edit);
            
                    btn_edit.addClass('ui-button').css({'font-size': '0.8em', 'height': '18px', 'max-width': '40px',
                            'min-width': '40px', padding: 0, 'margin-right': '10px', background: 'none'});
                            
                }else{
                    
                    btn_edit.button({showLabel:false, label:top.HR('Edit linked record'),
                                    icon:'ui-icon-pencil'})
                                .css({'font-size': '0.8em', height: '21px', 'max-width': '18px'})
                    
                }
                
                btn_edit
                    .attr('data-recID', info['rec_ID'])
                    .on('click', function(event){
           
            let recID = $(event.target).hasClass('ui-button')
                    ?$(event.target).attr('data-recID')
                    :$(event.target).parent('.ui-button').attr('data-recID');
          
            window.hWin.HEURIST4.ui.openRecordInPopup(recID, null, isEdit,
            {selectOnSave:true, edit_obstacle: true, onselect: 
                    function(event, res){
                        if(res && window.hWin.HEURIST4.util.isRecordSet(res.selection)){
                            let recordset = res.selection;
                            let record = recordset.getFirstRecord();
                            let rec_Title = window.hWin.HEURIST4.util.stripTags(recordset.fld(record,'rec_Title')); //was htmlEscape
                            
                            ele.find('span[data-recID='+recID+']').text(rec_Title);
                        }
                    }}
                );
                            
                        });
                        
            }else{
                btn_edit.hide();    
            }
        }
        
        $(container).find('.add-rel-button').hide();
        
        return ele;
    },
    
    
    //
    //
    //
    getRecordTitle: function(recID, callback){
        
            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recID, w: "e", f:"header"},  
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    let recordset = new HRecordSet(response.data);
                    if(recordset.length()>0){
                        let record = recordset.getFirstRecord();
                        if(window.hWin.HEURIST4.util.isFunction(callback)){
                            callback(recordset.fld(record,'rec_Title'));    
                        }
                    }
                }
            });
        
    },

    //
    //  List of languages defined in heurist config ini
    //  if $select is not define it return html string with <option>s
    //  It uses 3 chars codes: ISO639-2
    //  it is used in editTranslations and editCMS2
    //    
    createLanguageSelect: function($select, topOptions, defValue, showName, eventHandlers){
        
            let languages = window.hWin.HAPI4.sysinfo.common_languages;
            let content = [];
            
            let opts = topOptions?topOptions:[];
            let keys = Object.keys(languages);
            if(keys.length>0){
                for (let i in keys){ //ISO639-2 (alpha3)
                    let code = keys[i]; 
                    opts.push({key:code, title:(showName===false?code:languages[code]['name'])});
                    
                    content.push('<option value="'+code+'">'+(showName===false?code:languages[code]['name'])+'</option>');
                } // for
            }
            
            if($select==null){
                return content.join('');
            }else{
                $select.empty();
                window.hWin.HEURIST4.ui.fillSelector($select[0], opts);
                if(defValue){
                    $select.val( defValue );
                }
                return window.hWin.HEURIST4.ui.initHSelect($select[0], false, null, eventHandlers);
            }
            
    },

    createEncodingSelect: function($select, topOptions, defValue, useHtmlSelect = true){

        let encoding = [
            "UTF-8", "UTF-16", "UTF-16BE", "UTF-16LE", "CP1251", "CP1252", "KOI8-R", "UCS-4", "UCS-4BE", "UCS-4LE", "UCS-2", "UCS-2BE", "UCS-2LE", "UTF-32", "UTF-32BE", "UTF-32LE", "UTF-7", "UTF7-IMAP",
            "ASCII", "EUC-JP", "SJIS", "eucJP-win", "SJIS-win", "ISO-2022-JP", "ISO-2022-JP-MS", "CP932", "CP51932", "MacJapanese", "SJIS-DOCOMO", "SJIS-KDDI", "SJIS-SOFTBANK", "UTF-8-DOCOMO", "UTF-8-KDDI",
            "UTF-8-SOFTBANK", "ISO-2022-JP-KDDI", "JIS", "JIS-ms", "CP50220", "CP50220raw", "CP50221", "CP50222", "ISO-8859-1", "ISO-8859-2", "ISO-8859-3", "ISO-8859-4", "ISO-8859-5", "ISO-8859-6", "ISO-8859-7",
            "ISO-8859-8", "ISO-8859-9", "ISO-8859-10", "ISO-8859-13", "ISO-8859-14", "ISO-8859-15", "byte2be", "byte2le", "byte4be", "byte4le", "BASE64", "HTML-ENTITIES", "7bit", "8bit", "EUC-CN", "CP936", "GB18030", 
            "HZ", "EUC-TW", "CP950", "BIG-5", "EUC-KR", "UHC", "ISO-2022-KR", "CP866"
        ];
        
        let opts = topOptions ? topOptions : [];
        let content = [];

        for(const encode of encoding){
            content.push(`<option value="${encode}">${encode}</option>`);
            opts.push({key: encode, title: encode});
        }

        if($select == null){
            return content.join('');
        }else{
            $select.empty();
            window.hWin.HEURIST4.ui.fillSelector($select[0], opts);
            if(defValue){
                $select.val(defValue);
            }
            window.hWin.HEURIST4.ui.initHSelect($select[0], useHtmlSelect);
        }
    },

    //
    // $select jquery select
    //
    createTemplateSelector: function($select, topOptions, defValue, options){
        
        window.hWin.HAPI4.SystemMgr.reportAction({action:'list'}, 
            function(response){
                if (response.status == window.hWin.ResponseStatus.OK) {

                    let context = response.data;
                    
                    let opts = topOptions?topOptions:[];
                    if(context?.length>0){
                        for (let i=0; i<context.length; i++){
                            opts.push({key:context[i].filename, title:context[i].name});
                        } // for
                    }

                    window.hWin.HEURIST4.ui.fillSelector($select[0], opts);
                    if(defValue){
                        $select.val( defValue );
                    }
                    
                    window.hWin.HEURIST4.ui.initHSelect($select[0], false, null, options?.eventHandlers, options?.extraOptions);

                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

        });


    },
    
    //------------------------------------
    // configMode.entity
    // configMode.filter_group
    createEntitySelector: function(selObj, configMode, topOptions, callback){
        
        let request = {a:'search','details':'name'};
        let fieldTitle;
        
        if(configMode.entity=='SysUsers'){
            fieldTitle = 'ugr_Name';
            request['entity'] = 'sysUGrps';
            request['ugr_Type'] = 'user';
            request['ugl_GroupID'] = configMode.filter_group;
            
        }else if(configMode.entity=='SysGroups'){
            fieldTitle = 'ugr_Name';
            request['entity'] = 'sysUGrps';
            request['ugr_Type'] = 'workgroup';
            request['ugl_UserID'] = configMode.filter_group;
            
        }else if(configMode.entity=='DefTerms'){
            fieldTitle = 'trm_Label';
            request['entity'] = 'defTerms';
            request['trm_Domain'] = configMode.filter_group;
            request['trm_ParentTermID'] = [0,'NULL']; //get vocabs only
        }else if(configMode.entity=='defRecTypeGroups'){
            
            selObj = window.hWin.HEURIST4.ui.createRectypeGroupSelect(selObj, topOptions);
            return selObj;
            
        }else if(configMode.entity=='defDetailTypeGroups'){
            
            selObj = window.hWin.HEURIST4.ui.createDetailtypeGroupSelect(selObj, topOptions);
            return selObj;

        }else if(configMode.entity=='defVocabularyGroups'){
            
            selObj = window.hWin.HEURIST4.ui.createVocabularyGroupSelect(selObj, topOptions);
            return selObj;
            
/*            
        }else if(configMode.entity=='DefRecTypeGroups'){
            fieldTitle = 'rtg_Name';
            request['entity'] = 'defRecTypeGroups';
            
        }else if(configMode.entity=='DefDetailTypeGroups'){
            fieldTitle = 'dtg_Name';
            request['entity'] = 'defDetailTypeGroups';
            
        }else if(configMode.entity=='DefRecTypeGroups'){
            fieldTitle = 'rtg_Name';
            request['entity'] = 'defRecTypes';
            request['rty_RecTypeGroupID'] = configMode.filter_group;
            
        }else if(configMode.entity=='DefDetailTypeGroups'){
            fieldTitle = 'dtg_Name';
            request['entity'] = 'defDetailTypes';
            request['dty_DetailTypeGroupID'] = configMode.filter_group;
*/          
        }else if(configMode.entity=='SysImportFiles'){
            fieldTitle = 'sif_TempDataTable';
            request['entity'] = 'sysImportFiles';
            request['ugr_ID'] = configMode.filter_group;
        }

        selObj = window.hWin.HEURIST4.ui.createSelector(selObj, null);
        
        if(request['entity']){
        
            window.hWin.HAPI4.EntityMgr.doRequest(request,
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            let groups = new HRecordSet(response.data).makeKeyValueArray(fieldTitle);

                            if(configMode.entity == 'SysImportFiles'){
                                for(const idx in groups){
                                    if(!Object.hasOwn(groups, idx)){
                                        continue;
                                    }
                                    groups[idx]['title'] = groups[idx]['title'].replaceAll(/  +/g, '&nbsp;&nbsp;');
                                }
                            }

                            if(!Array.isArray(topOptions)){
                                if(topOptions==true){
                                    topOptions = [{key:'',title:window.hWin.HR('select...')}];
                                }else if(!window.hWin.HEURIST4.util.isempty(topOptions) && topOptions!==false){
                                    if(topOptions===true) topOptions ='';
                                    topOptions = [{key:'',title:topOptions}];
                                }
                            }
                            if(Array.isArray(topOptions) && Array.isArray(groups)){
                                groups = topOptions.concat(groups);
                            }else if(Array.isArray(topOptions)){
                                groups = topOptions;
                            }

                            selObj = window.hWin.HEURIST4.ui.createSelector(selObj, groups);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                        
                        if(window.hWin.HEURIST4.util.isFunction(callback)){
                            callback(selObj);
                        }
                        
                    });
                              
        }
        return selObj;
    },
    
    //
    // checks wether the appropriate javascript is loaded
    //
    showEntityDialog: function(entityName, options){
        
        entityName = entityName.charAt(0).toUpperCase() + entityName.slice(1);
                            
        let widgetName = 'manage'+entityName;
        
        
        if(window.hWin.HEURIST4.util.isFunction($('body')[widgetName])){ //OK! widget script js has been loaded
        
            return window.hWin.HEURIST4.ui.showWdigetDialog(widgetName, options);
            
        }else{
            
            let path = window.hWin.HAPI4.baseURL + 'hclient/widgets/entity/';
            let scripts = [ path+widgetName+'.js'];
            
            //entities without search option
            if(!(entityName=='UsrBookmarks' || 
                 entityName=='SysIdentification' ||
                 entityName=='DefDetailTypeGroups' || 
                 entityName=='DefRecTypeGroups' || 
                 entityName=='DefRecStructure' || 
                 entityName=='DefTerms' || 
                 entityName=='DefVocabularyGroups' || 
                 entityName=='SysBugreport')){ 
                scripts.push(path+'search'+entityName+'.js');
            }
            
            //load missed javascripts
            $.getMultiScripts(scripts)
            .done(function() {
                // all done
                window.hWin.HEURIST4.ui.showEntityDialog(entityName, options);
            }).fail(function(error) {
                // one or more scripts failed to load
                window.hWin.HEURIST4.msg.showMsg_ScriptFail();
            }).always(function() {
                // always called, both on success and error
            });
            
        }
    },
    
    //
    //
    //    
    showWdigetDialog: function( widgetName, options ){
        
        if(!window.hWin.HEURIST4.util.isFunction($('body')[widgetName])){
            window.hWin.HEURIST4.msg.showMsg_ScriptFail();
            return null;
        }

        let manage_dlg = null; 
        
        if(!options) options = {};
        if(options.isdialog!==false) options.isdialog = true; //by default popup      
        
        if(!options.container){ //container not defined - add new one to body
        
            manage_dlg = $('<div id="heurist-dialog-'+widgetName+'-'+window.hWin.HEURIST4.util.random()+'">')
                .appendTo( $('body') );
            manage_dlg[widgetName]( options );
        }else{
            if($(options.container)[widgetName]('instance')){
                $(options.container)[widgetName]('destroy');
            }
            $(options.container).empty();
            $(options.container).show();
            
            if(!$(options.container).attr('id')){
                $(options.container).uniqueId()
            }
            
            manage_dlg = $(options.container)[widgetName]( options );
        }
        
        return manage_dlg;        
    },
    
    //
    //
    //
    showPublishDialog: function( options ){
        
        //OK! script as been loaded
        if( typeof HPublishDialog==='undefined' || !window.hWin.HEURIST4.util.isFunction(HPublishDialog)){        
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/framecontent/publishDialog.js?t'
                        +window.hWin.HEURIST4.util.random(),  
                function(){ 
                        window.hWin.HEURIST4.ui.showPublishDialog( options );
                }
            );        
        }else{
            if(!window.hWin.HEURIST4.ui.publishDlg) {
                window.hWin.HEURIST4.ui.publishDlg = new HPublishDialog();                        
            }
            window.hWin.HEURIST4.ui.publishDlg.openPublishDialog( options );
        }
        
    },

    //
    //
    //
    initEditSymbologyControl: function(element, value){

                element.attr('readonly','readonly');
                
                value = window.hWin.HEURIST4.util.isJSON(value);
                if (value) {
                    element.val(JSON.stringify(value));   
                }
                
                let parent_div = element.parent(); //$dlg.find(sname+'_div')
        
                $('<span>')
                .addClass("smallbutton ui-icon ui-icon-circlesmall-close")
                .attr('tabindex', '-1')
                .attr('title', 'Reset default symbology')
                .appendTo( parent_div )
                .css({'line-height': '20px',cursor:'pointer',
                    outline: 'none','outline-style':'none', 'box-shadow':'none',  'border-color':'transparent'})
                    .on( { click: function(){ window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                        function(){element.val('');}); }});
                
                let $btn_edit_switcher = $( '<span>open editor</span>', {title: 'Open symbology editor'})
                    .addClass('smallbutton btn_add_term')
                    .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                    .appendTo( parent_div );
                
                $btn_edit_switcher.on( { click: function(){
                        let current_val = window.hWin.HEURIST4.util.isJSON( element.val() );
                        if(!current_val) current_val = {};
                        window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_val, 0, function(new_value){
                            element.val(JSON.stringify(new_value));
                        });
                }});
        
    },

    
    //
    // show map style edit dialog
    //
    showEditSymbologyDialog: function(current_value, mode_edit, callback){
        //todo optionally load dynamically editing_exts.js
        editSymbology(current_value, mode_edit, callback);
    },

    //
    // show map style edit dialog
    //
    showImgFilterDialog: function(current_value, callback){
        //todo optionally load dynamically editing_exts.js
        if(typeof imgFilter !== 'undefined' && window.hWin.HEURIST4.util.isFunction(imgFilter)){  // already loaded
            imgFilter( current_value, callback );    
        }else{
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/editing/imgFilter.js', function(){
                window.hWin.HEURIST4.ui.showImgFilterDialog(current_value, callback);
            }); 
        }
    },
    
    //
    // show heurist theme dialog
    //
    showEditThemeDialog: function(current_value, needName, callback){
        //todo optionally load dynamically editTheme.js
        if(typeof editTheme !== 'undefined' && window.hWin.HEURIST4.util.isFunction(editTheme)){
            editTheme(current_value, callback);    
        }else{
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/editing/editTheme.js', function(){
                window.hWin.HEURIST4.ui.showEditThemeDialog(current_value, needName, callback);
            }); 
        }
    },

    //
    // show edit cms in new browser tab
    //
    showEditCMSwin: function( options ){
        
        let surl = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&website&pageid=';
       
        if( window.hWin.HEURIST4.util.isNumber( options ) ){
            surl = surl + options;
        }else{
            surl = surl + options.record_id;
        }
        
        surl = surl + '&edit=2';
            
        window.open(surl, '_blank');
    },
    
    //
    // show action dialog based on 
    //   recordAction widgets (see widget/records) or 
    //   cms/embedDialog widget
    //   
    //
    showRecordActionDialog: function(actionName, options){

        
        if(!options) options = {};
        if(options.isdialog!==false) options.isdialog = true; //by default popup      

        let  doc_body = $(window.hWin.document).find('body');
        
        let widgetName = actionName;
        
        if(actionName.indexOf('db')===0){
            widgetName = 'dbAction';
            options['actionName'] = actionName;
        }
        
        if(window.hWin.HEURIST4.util.isFunction(doc_body[widgetName])){ //OK! widget script js has been loaded
        
            let manage_dlg;
            
            let container_name = 'heurist-dialog-'+widgetName;
            if(!options.keep_instance){
                container_name += '-'+window.hWin.HEURIST4.util.random();
            }

            if(!options.container){
                options.container = $('#'+container_name);
            }
            
            if(!options.container || options.container.length==0){ //container not defined - add new one to body
            
                manage_dlg = $('<div id="'+container_name+'">')
                    .appendTo( doc_body );
                manage_dlg[widgetName]( options );
            }else{
                
                if($(options.container)[widgetName]('instance')){
                    
                    if(options.keep_instance){
                        $(options.container)[widgetName]( options ); //.show();
                        return;
                    }else{
                        $(options.container)[widgetName]('destroy');
                    }
                    
                }
                $(options.container).empty();
                $(options.container).show();
                
                manage_dlg = $(options.container)[widgetName]( options );
            }
            
            return manage_dlg;
        
        }else{
            
            //options.path
            
            let path = window.hWin.HAPI4.baseURL + 'hclient/'
                +(options.path?options.path:'widgets/record/');
            
            let scripts = [ path+actionName+'.js'];
            if(actionName=='recordAdd'){
                scripts = [path+'recordAccess.js', path+'recordAdd.js'];
            }else if( actionName=='thematicMapping'){
                scripts = [window.hWin.HAPI4.baseURL + 'hclient/widgets/entity/popups/'+actionName+'.js'];
            }else if(actionName=='dbCreate'){
                scripts = [window.hWin.HAPI4.baseURL + 'hclient/widgets/baseAction.js', 
                           window.hWin.HAPI4.baseURL + 'hclient/widgets/database/dbAction.js'];
            }else if((actionName=='lookupConfig' || actionName=='repositoryConfig') && !window.hWin.HEURIST4.util.isFunction(doc_body['baseConfig'])){
                scripts.unshift(`${window.hWin.HAPI4.baseURL}hclient/widgets/baseConfig.js`);
            }
            
            $.getMultiScripts(scripts)
            .done(function() {
                // all done
                window.hWin.HEURIST4.ui.showRecordActionDialog(actionName, options);
            }).fail(function(error) {
                // one or more scripts failed to load
                window.hWin.HEURIST4.msg.showMsg_ScriptFail();
            }).always(function() {
                // always called, both on success and error
            });
        }
    },    
    
    //
    // 
    getRidGarbageHelp: function(help_text){

        //get rid of garbage help text
        if (window.hWin.HEURIST4.util.isnull(help_text) ||
            help_text.indexOf('Please rename to an appropriate heading')==0 || 
            help_text.indexOf('Please document the nature of this detail type')==0 ||
            help_text=='Another separator field' ||
            help_text=='Headings serve to break the data entry form up into sections'){
                
            help_text='';
        }
        
       
        return window.hWin.HEURIST4.util.stripTags(help_text,'a, u, i, b, br, strong');        
    },
    
    
    getMousePos: function(e){

        let posx = 0;
        let posy = 0;
        if (!e) e = window.event;
        if (e.pageX || e.pageY)     {
            posx = e.pageX;
            posy = e.pageY;
        }
        else if (e.clientX || e.clientY)     {
            posx = e.clientX
            + document.documentElement.scrollLeft;
            posy = e.clientY
            + document.documentElement.scrollTop;
        }

        return [posx, posy];
    },
    
    
    validateName: function(name, lbl, maxlen){
      
            let swarn = "";

            name = name.toLowerCase();
            if( name=="id" || name=="modified" || name=="rectitle"){
                   swarn = lbl+", you defined, is a reserved word. Please try an alternative";
            //}else if (name.indexOf('.')>=0 ) {  //regex.test(name)
            }else if (name!=''  && !(/^[^.'"}{\[\]]+$/.test(name))) {
                   swarn = lbl+" contains . [ ] { } ' \" restricted characters which are not permitted in this context. Please use alphanumeric characters.";
            }else if (name.indexOf('<')>=0 && name.indexOf('<')< name.indexOf('>') ) {
                   swarn = lbl+" contains '<>' characters which are not permitted in this context. Please use alphanumeric characters.";
            }else
            if(maxlen>0  && name.length>maxlen){
                swarn = 'Sorry, '+lbl+' exceeds the maximum allowed length - '+maxlen+' characters - by '
                    +(name.length-maxlen)+' characters. Please reduce length.';                
            }
            
            return swarn;
    },
        
    //
    // prevents entering restricted characters
    //
    preventChars: function(event){

        event = event || window.event;
        let charCode = typeof event.which == "number" ? event.which : event.keyCode;
        if (charCode && charCode > 31)
        {
            let keyChar = event.key?event.key:String.fromCharCode(charCode);
            // Old test only allowed specific characters, far too restrictive. New test only restrcts characters which will pose a problem
            // if(!/^[a-zA-Z0-9$_<> /,–—]+$/.test(keyChar)){
            let sWarn = '';
            
            let value = $(event.target).val();
            if((value.indexOf('<')>=0 && keyChar=='>') || 
               (value.indexOf('>')>0 && keyChar=='<')){
                   sWarn = 'Both < and > are forbid';
            }else
            if(/^[{}"\[\]]+$/.test(keyChar)){ // . and ' are allowed
                sWarn = 'Restricted characters: [ ] { } " '; // .'
            }
            
            if(sWarn!=''){
                event.returnValue = false;
                let trg = event.target;
                
                window.hWin.HEURIST4.util.stopEvent(event);
                
                window.hWin.HEURIST4.msg.showMsgFlash(sWarn,700,null, trg);
                setTimeout(function(){
                        $(trg).trigger('focus');
                }, 750);
                
                return false;
            }
        }
        return true;
    },    
    
    //
    //
    //
    preventNonNumeric: function(evt) {
        let theEvent = evt || window.event;
        let key = theEvent.keyCode || theEvent.which;
        if(key==37 || key==39) return;
        key = String.fromCharCode( key );
        let regex = /[0-9]|\./;
        if( !regex.test(key) ) {
            window.hWin.HEURIST4.util.stopEvent(theEvent);
        }
    },
    
    //
    //
    //
    preventNonAlphaNumeric: function(evt) {
        let theEvent = evt || window.event;
        let keyCode = theEvent.keyCode || theEvent.which;
        //if(key==37 || key==39) return;  // % '
        if(keyCode > 31){
            let key = String.fromCharCode( keyCode );
            let is_percent = keyCode === 53 && theEvent.key === '%';
            let is_underscore = keyCode === 189 && theEvent.key === '_';
            if(is_percent || (!is_underscore && !/^[a-zA-Z0-9_]+$/.test(key))){
                window.hWin.HEURIST4.util.stopEvent(theEvent);
            }
        }
        return true;        
    },
    
    cleanFilename: function(filename) {
        filename = filename.replace(/\s+/gi, '-'); // Replace white space with dash
        filename= filename.split(/[^a-zA-Z0-9\-\_\.]/gi).join('_');
        return filename;
    },

    //
    //
    //
    rgbToHex: function (r, g, b) {
        function __componentToHex(c) {
          let hex = c.toString(16);
          return hex.length == 1 ? "0" + hex : hex;
        }
      return "#" + __componentToHex(r) + __componentToHex(g) + __componentToHex(b);
    },
    
    
    hexToRgb: function (hex) {

      if(window.hWin.HEURIST4.util.isempty(hex)){
          return null;
      }else{
          // Expand shorthand form (e.g. "03F") to full form (e.g. "0033FF")
          const shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
          hex = hex.replace(shorthandRegex, (m, r, g, b) => {
            return r + r + g + g + b + b;
          });
            
          const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
          return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
          } : null;
      }
    },
    
    hexToRgbStr: function (hex, opacity) {
        
        let rgb = window.hWin.HEURIST4.ui.hexToRgb(hex);
        if(rgb!=null){

            if(opacity>1){
                opacity = parseInt(opacity)/100;
            }
            
            if(opacity>0 && opacity<1){
                return 'rgba('+rgb.r+','+rgb.g+','+rgb.b+','+opacity+')';
            }else{
                return 'rgb('+rgb.r+','+rgb.g+','+rgb.b+')';
            }
        }else{
            return null;
        }
    },
    // returns hex rgb by name
    // see getColorFromTermValue  
    //
    getColorArr: function(x) {
      if (x == "names") {return ['aliceblue','antiquewhite','aqua','aquamarine','azure','beige','bisque','black','blanchedalmond','blue','blueviolet','brown','burlywood','cadetblue','chartreuse','chocolate','coral','cornflowerblue','cornsilk','crimson','cyan','darkblue','darkcyan','darkgoldenrod','darkgray','darkgrey','darkgreen','darkkhaki','darkmagenta','darkolivegreen','darkorange','darkorchid','darkred','darksalmon','darkseagreen','darkslateblue','darkslategray','darkslategrey','darkturquoise','darkviolet','deeppink','deepskyblue','dimgray','dimgrey','dodgerblue','firebrick','floralwhite','forestgreen','fuchsia','gainsboro','ghostwhite','gold','goldenrod','gray','grey','green','greenyellow','honeydew','hotpink','indianred','indigo','ivory','khaki','lavender','lavenderblush','lawngreen','lemonchiffon','lightblue','lightcoral','lightcyan','lightgoldenrodyellow','lightgray','lightgrey','lightgreen','lightpink','lightsalmon','lightseagreen','lightskyblue','lightslategray','lightslategrey','lightsteelblue','lightyellow','lime','limegreen','linen','magenta','maroon','mediumaquamarine','mediumblue','mediumorchid','mediumpurple','mediumseagreen','mediumslateblue','mediumspringgreen','mediumturquoise','mediumvioletred','midnightblue','mintcream','mistyrose','moccasin','navajowhite','navy','oldlace','olive','olivedrab','orange','orangered','orchid','palegoldenrod','palegreen','paleturquoise','palevioletred','papayawhip','peachpuff','peru','pink','plum','powderblue','purple','rebeccapurple','red','rosybrown','royalblue','saddlebrown','salmon','sandybrown','seagreen','seashell','sienna','silver','skyblue','slateblue','slategray','slategrey','snow','springgreen','steelblue','tan','teal','thistle','tomato','turquoise','violet','wheat','white','whitesmoke','yellow','yellowgreen']; }
/*      
      if (x == "names") {return ['AliceBlue','AntiqueWhite','Aqua','Aquamarine','Azure','Beige','Bisque','Black','BlanchedAlmond','Blue','BlueViolet','Brown','BurlyWood','CadetBlue','Chartreuse','Chocolate','Coral','CornflowerBlue','Cornsilk','Crimson','Cyan','DarkBlue','DarkCyan','DarkGoldenRod','DarkGray','DarkGrey','DarkGreen','DarkKhaki','DarkMagenta','DarkOliveGreen','DarkOrange','DarkOrchid','DarkRed','DarkSalmon','DarkSeaGreen','DarkSlateBlue','DarkSlateGray','DarkSlateGrey','DarkTurquoise','DarkViolet','DeepPink','DeepSkyBlue','DimGray','DimGrey','DodgerBlue','FireBrick','FloralWhite','ForestGreen','Fuchsia','Gainsboro','GhostWhite','Gold','GoldenRod','Gray','Grey','Green','GreenYellow','HoneyDew','HotPink','IndianRed','Indigo','Ivory','Khaki','Lavender','LavenderBlush','LawnGreen','LemonChiffon','LightBlue','LightCoral','LightCyan','LightGoldenRodYellow','LightGray','LightGrey','LightGreen','LightPink','LightSalmon','LightSeaGreen','LightSkyBlue','LightSlateGray','LightSlateGrey','LightSteelBlue','LightYellow','Lime','LimeGreen','Linen','Magenta','Maroon','MediumAquaMarine','MediumBlue','MediumOrchid','MediumPurple','MediumSeaGreen','MediumSlateBlue','MediumSpringGreen','MediumTurquoise','MediumVioletRed','MidnightBlue','MintCream','MistyRose','Moccasin','NavajoWhite','Navy','OldLace','Olive','OliveDrab','Orange','OrangeRed','Orchid','PaleGoldenRod','PaleGreen','PaleTurquoise','PaleVioletRed','PapayaWhip','PeachPuff','Peru','Pink','Plum','PowderBlue','Purple','RebeccaPurple','Red','RosyBrown','RoyalBlue','SaddleBrown','Salmon','SandyBrown','SeaGreen','SeaShell','Sienna','Silver','SkyBlue','SlateBlue','SlateGray','SlateGrey','Snow','SpringGreen','SteelBlue','Tan','Teal','Thistle','Tomato','Turquoise','Violet','Wheat','White','WhiteSmoke','Yellow','YellowGreen']; }
*/      
      if (x == "hexs") {return ['f0f8ff','faebd7','00ffff','7fffd4','f0ffff','f5f5dc','ffe4c4','000000','ffebcd','0000ff','8a2be2','a52a2a','deb887','5f9ea0','7fff00','d2691e','ff7f50','6495ed','fff8dc','dc143c','00ffff','00008b','008b8b','b8860b','a9a9a9','a9a9a9','006400','bdb76b','8b008b','556b2f','ff8c00','9932cc','8b0000','e9967a','8fbc8f','483d8b','2f4f4f','2f4f4f','00ced1','9400d3','ff1493','00bfff','696969','696969','1e90ff','b22222','fffaf0','228b22','ff00ff','dcdcdc','f8f8ff','ffd700','daa520','808080','808080','008000','adff2f','f0fff0','ff69b4','cd5c5c','4b0082','fffff0','f0e68c','e6e6fa','fff0f5','7cfc00','fffacd','add8e6','f08080','e0ffff','fafad2','d3d3d3','d3d3d3','90ee90','ffb6c1','ffa07a','20b2aa','87cefa','778899','778899','b0c4de','ffffe0','00ff00','32cd32','faf0e6','ff00ff','800000','66cdaa','0000cd','ba55d3','9370db','3cb371','7b68ee','00fa9a','48d1cc','c71585','191970','f5fffa','ffe4e1','ffe4b5','ffdead','000080','fdf5e6','808000','6b8e23','ffa500','ff4500','da70d6','eee8aa','98fb98','afeeee','db7093','ffefd5','ffdab9','cd853f','ffc0cb','dda0dd','b0e0e6','800080','663399','ff0000','bc8f8f','4169e1','8b4513','fa8072','f4a460','2e8b57','fff5ee','a0522d','c0c0c0','87ceeb','6a5acd','708090','708090','fffafa','00ff7f','4682b4','d2b48c','008080','d8bfd8','ff6347','40e0d0','ee82ee','f5deb3','ffffff','f5f5f5','ffff00','9acd32']; }
    },
    
    //
    // Returns array of color gradients
    //
    getColourGradient: function (startColour, endColour, steps){

        function __map(value, fromSource, toSource, fromTarget, toTarget) {
            return (value - fromSource) / (toSource - fromSource) * (toTarget - fromTarget) + fromTarget;
        }

        function __getColour(startColour, endColour, min, max, value) {
            
            let startRGB = window.hWin.HEURIST4.ui.hexToRgb(startColour);
            let endRGB = window.hWin.HEURIST4.ui.hexToRgb(endColour);

            let percentFade = __map(value, min, max, 0, 1);

            let diffRed = endRGB.r - startRGB.r;
            let diffGreen = endRGB.g - startRGB.g;
            let diffBlue = endRGB.b - startRGB.b;

            diffRed = (diffRed * percentFade) + startRGB.r;
            diffGreen = (diffGreen * percentFade) + startRGB.g;
            diffBlue = (diffBlue * percentFade) + startRGB.b;

            let result = window.hWin.HEURIST4.ui.rgbToHex(Math.round(diffRed), Math.round(diffGreen), Math.round(diffBlue));
            
            return result;
        }

        let res = [startColour];
        for(let i=0; i<steps; i++){
            let value = (i + 1) % steps;
            res.push( __getColour(startColour, endColour, 0, steps, value) );   
        }
        res[res.length-1] = endColour;
        return res;
    },
    

  wait_timeout:0,
  wait_callback:null,
  wait_ms:3000,
  wait_terminated:false,
  
  //
  // clear timeout and starts new one
  // it is called on server call and user change
  //
  onInactiveReset: function(doNotStartAgain){
      
        if(window.hWin.HEURIST4.ui.wait_timeout) clearTimeout(window.hWin.HEURIST4.ui.wait_timeout);
        window.hWin.HEURIST4.ui.wait_timeout = 0;

        let events = ['mousedown', 'keydown', 'scroll', 'touchstart', 'focus']; //'mousemove', 
        events.forEach(function(name) {
            window.hWin.document.removeEventListener(name, window.hWin.HEURIST4.ui.onInactiveReset); 
        });    
        
        if(doNotStartAgain===true) {
            window.hWin.HEURIST4.wait_terminated = true;
            window.hWin.HEURIST4.ui.wait_ms = 0;
            return;   
        }

        //start again        
        if(!window.hWin.HEURIST4.wait_terminated && window.hWin.HEURIST4.ui.wait_ms>0){
            window.hWin.HEURIST4.ui.onInactiveStart();    
        }
        
  },

  //  NOT USED at the moment
  //  interval function that call "cb" function after "ms" milliseconds of user idleness
  //  it is used to verify credentials
  //
  onInactiveStart: function(ms, cb){
      
      if(!window.hWin.HEURIST4.ui.wait_timeout){
          if(!isNaN(ms) && Number(ms)>0){
             window.hWin.HEURIST4.wait_terminated = false;
             window.hWin.HEURIST4.ui.wait_ms = ms;
          }
          if(window.hWin.HEURIST4.util.isFunction(cb)){
             window.hWin.HEURIST4.wait_callback = cb;
          }
          if(window.hWin.HEURIST4.util.isFunction(window.hWin.HEURIST4.wait_callback)){

             window.hWin.HEURIST4.ui.wait_timeout = setTimeout(
                  function(){
                      if(!window.hWin.HEURIST4.wait_terminated){
                          window.hWin.HEURIST4.wait_callback();
                      }
                  }, window.hWin.HEURIST4.ui.wait_ms); 

              let events = ['mousedown', 'keypress', 'scroll', 'touchstart', 'focus']; //'mousemove', 
              events.forEach(function(name) {
                        window.hWin.document.addEventListener(name, window.hWin.HEURIST4.ui.onInactiveReset, true); 
              });        

          }
      }
  },
  
  // @todo reimplement
  // enlarge an image, usually, for when it is clicked
  //
  showPlayer: function(obj, container, id, url){
    let that = this;
    let currentImg = obj;

    if ($(container).hasClass("thumb_image")){  //thumb to player
        currentImg.style.display = 'none';

        $(container).toggleClass("thumb_image fullSize");
        
        //add content to player div
        $.ajax({
            url: url,
            type: "GET",
            cache: false,
            fail: function(jqXHR, textStatus, errorThrown ) {
            },
            success: function( response, textStatus, jqXHR ){
                let obj = jqXHR.responseText;
                if (obj){
                    let  elem = container.querySelector('#player'+id);
                    elem.innerHTML = obj;
                    elem.style.display = 'block';

                    //calculate width for data and image                             
                    let player = elem;
                    if($(player.parentNode.parentNode).hasClass('production')){

                        let img = $(player).find('img');
                        if(img.length>0){

                            img = img[0];

                            function __onImgLoaded(){

                                let w_img = $(img).width();
                                let w_win = window.innerWidth;

                                if(w_win-w_img<600){
                                    //draw image above data
                                    player.parentNode.parentNode.style.float = 'none';
                                    //$('#div_public_data').css('max-width', w_win-40);    
                                }else{
                                    //draw on the right and reduce max width for data
                                    player.parentNode.parentNode.style.float = 'right';
                                   
                                }

                            }

                            if(img.complete){
                                __onImgLoaded();
                            }else{
                                img.addEventListener('load', __onImgLoaded)
                            }
                        }

                    }
                    function __closePlayer(){
                        that.hidePlayer( id, container );            
                    }

                    $(elem).find('img').on('click', __closePlayer );

                    // Display the show thumbnail anchor
                    elem = (container.querySelector('#lnk'+id)) ? container.querySelector('#lnk'+id) : container.parentNode.querySelector('#lnk'+id);
                    elem.style.display = 'inline-block';
                    elem.onclick = __closePlayer;
                }
            }
        });

    }
    else{	// player to thumb
        this.hidePlayer( id, container );
    }

  },

  hidePlayer: function(id, container){
    //clear and hide player div
    let  elem = container.querySelector('#player'+id);
    elem.innerHTML = '';
    elem.style.display = 'none';

    //hide show tumbnail link
    elem = (container.querySelector('#lnk'+id)) ? container.querySelector('#lnk'+id) : container.parentNode.querySelector('#lnk'+id);
    elem.style.display = 'none';

    //show thumbnail
    elem = container.querySelector('#img'+id);
    $(container).toggleClass("thumb_image fullSize");

    elem.style.display = 'inline-block';

    //restore
    if($(elem.parentNode.parentNode).hasClass('production')){
        $(elem.parentNode.parentNode).css({'float':'right'});
        /* Remarked 2019-01-24
        var w_win = window.innerWidth;
        $('#div_public_data').css('max-width', w_win-250);    
        */
    }
  },

  //
  //
  //  
  disableAutoFill: function(ele){

      ele.attr('autocomplete','off') //disabled
      .attr('autocorrect','off')
      .attr('autocapitalize','none')
      .attr('spellcheck','false');

  },

    //
    // Reorder the fancytree nodes for record field trees
    // tree => Fancytree element
    // order => order for nodes
    //      0 : display order (default)
    //      1 : display name
    //
    reorderFancytreeNodes_rst: function($tree_element, order){

        if($tree_element.length > 0 && $tree_element.fancytree('instance') !== undefined){

            let root = $tree_element.fancytree('getRootNode');

            root.sortChildren((a, b) => {

                if(!Number.isInteger(+a.data.dtyID_local) || !Number.isInteger(+b.data.dtyID_local)){
                    return 0;
                }

                let a_rtyid = (a.data.rt_ids) ? a.data.rt_ids : a.data.code.split(':')[0];
                let b_rtyid = (b.data.rt_ids) ? b.data.rt_ids : b.data.code.split(':')[0];

                if(!Number.isInteger(+a_rtyid) || !Number.isInteger(+b_rtyid) || a_rtyid != b_rtyid){
                    return 0;
                }

                let a_dtls = $Db.rst(a_rtyid, a.data.dtyID_local);
                let b_dtls = $Db.rst(b_rtyid, b.data.dtyID_local);

                if(!a_dtls || !b_dtls){
                    return 0;
                }
                if(order == 1){
                    return (a_dtls['rst_DisplayName'].toLowerCase()<b_dtls['rst_DisplayName'].toLowerCase())?-1:1;
                }else{
                    return (a_dtls['rst_DisplayOrder']<b_dtls['rst_DisplayOrder'])?-1:1;
                }
            }, true);
        }
    },
  
    //
    //
    //  options
    //     selector - for images, by default a[data-fancybox="home-roll-images"]
    //        OR
    //     content - array of objects {title:, img: }    
    //     maxWidth 
    //     maxHeight  - 430px
    //     duration - 7000 ms
    //     fade_duration - 300 ms
    //     showTitle - false 
    //
    //
    initGalleryContainer: function(container, options)
    {
        options = $.extend(options, {selector:'a[data-fancybox="home-roll-images"]'
                            ,maxHeight:'430px',duration:5000,fade_duration:300});
                            
        let imgs = [];
        
        if(!options.content){
        
            //image rollover with effects
            container.find('.gallery-slideshow').hide();

            let selector = '.gallery-slideshow > '+options.selector;
            let thumbs = container.find(selector); //all thumbnails
            
            options.content = [];
            $(thumbs).each(function(idx, alink){
                options.content.push( {title:$(alink).attr('title') ,img:$(alink).attr('href') });
            });
        }else{
            container.empty();
        }
        
        
        let title_ele = $('<h4>').css({'background-color': 'rgba(0,0,0,0.65)', bottom: 0,
            color: '#fff', left: 0, margin:'0.2em 0em', padding: '0.75em 1em', position: 'absolute', 'z-index':9999})
            .appendTo( container );
        if(!options.showTitle){
            title_ele.hide();
        }

        //init slide show
        let idx = 0;
        function __onImageLoad(){

            setInterval(function(){                               
                let cur_idx = idx;
                idx = (idx == imgs.length-1) ?0 :(idx+1);
                $(imgs[idx]).show();
                $(imgs[cur_idx]).hide('fade', null, options.fade_duration, function(){
                    if(options.showTitle){
                        title_ele.html(imgs[idx].attr('title'));    
                    }
                });
                /*
                $(imgs[idx]).hide('fade', null, (options.fade_duration-100), function(){
                    idx = (idx == imgs.length-1) ?0 :(idx+1);
                    //show next one
                    $(imgs[idx]).show('fade', null, options.fade_duration);        
                    if(options.showTitle){
                        title_ele.html(imgs[idx].attr('title'));    
                    }
                });
                */
                }, options.duration);                  
        }
        

        //load full images
        let z_index = options.content.length;
        $.each(options.content, function(idx, item){
            //add images
            let img = $('<img>').css({'z-index':z_index,'max-height':options.maxHeight,'position':'absolute'}).appendTo( container );
            z_index--;  
            if(options.maxWidth){
                img.css('max-width',options.maxWidth);
            }
            img.attr('title', item.title);
            
            imgs.push(img);

            if(idx>0){ //hide all except first one
                img.hide().attr('src',item.img);
            }else{
                title_ele.html(item.title);    
                img.load(__onImageLoad).attr('src',item.img); 
            } 
        });

        /* fancybox gallery
        if($.fancybox && window.hWin.HEURIST4.util.isFunction($.fancybox)){
        $.fancybox({selector : selector,
        loop:true, buttons: [
        "zoom",
        "slideShow",
        "thumbs",
        "close"
        ]});
        }
        */

    },
    
    //
    //
    //    
    prepareMapSymbol: function(style, def_style){
            
        if(!def_style){
            //'#00b0f0' - lighty blue
            def_style = {iconType:'rectype', color:'#ff0000', fillColor:'#ff0000', weight:3, opacity:1, 
                    dashArray: '',
                    fillOpacity:0.2, iconSize:18, stroke:true, fill:true};
        }
        
        if(!style) style = {};
        if(!style.iconType || style.iconType=='default') style.iconType = def_style.iconType;
        if(!(style.iconType=='url' && typeof style.iconSize == 'string' && style.iconSize.indexOf(',')>0)){
            style.iconSize = (style.iconSize>0) ?parseInt(style.iconSize) :def_style.iconSize;
        }
        style.color = (style.color?style.color:def_style.color);   //light blue
        style.fillColor = (style.fillColor?style.fillColor:def_style.fillColor);   //light blue
        style.weight = (window.hWin.HEURIST4.util.isNumber(style.weight) && style.weight>=0) ?style.weight :def_style.weight;
        style.opacity = (window.hWin.HEURIST4.util.isNumber(style.opacity) && style.opacity>=0) ?style.opacity :def_style.opacity;
        style.fillOpacity = (window.hWin.HEURIST4.util.isNumber(style.fillOpacity) && style.fillOpacity>=0) ?style.fillOpacity :def_style.fillOpacity;
        
        style.fill = window.hWin.HEURIST4.util.isnull(style.fill)?def_style.fill:style.fill;
        style.fill = window.hWin.HEURIST4.util.istrue(style.fill);
        style.stroke = window.hWin.HEURIST4.util.isnull(style.stroke)?def_style.stroke :style.stroke;
        style.stroke = window.hWin.HEURIST4.util.istrue(style.stroke);

        if(style.stroke){
            style.dashArray = window.hWin.HEURIST4.util.isnull(style.dashArray)?def_style.dashArray:style.dashArray;
        }
        
        if(!style.iconFont && def_style.iconFont){
            style.iconFont = def_style.iconFont;
        }
        
        //opacity accepts values 0~1 so need to 
        if(style.fillOpacity>1){
            style.fillOpacity = style.fillOpacity/100;
        }
        if(style.opacity>1){
            style.opacity = style.opacity/100;
        }
        
        return style;        
    }
    
}//end ui

}

/*
hSelect - decendant of jquery.selectmenu
*/
$.widget( "heurist.hSelect", $.ui.selectmenu, {
    
   _renderButtonItem: function( item ) {
      let buttonItem = $( "<span>", {
        "class": "ui-selectmenu-text"
      })
     
      buttonItem.html(item.label).css({'min-height': '17px'});
     
      if(window.hWin.HEURIST4.util.isColor(item.value)){
            buttonItem.css( "background-color", item.value )
      }else{
            buttonItem.css( "background-color", 'none' )
      }
     
      return buttonItem;
   },   
   /*
   _renderMenu: function( ul, items ) {
        this._super();
   },
   */
  _renderItem: function( ul, item ) {
    let li = $( "<li>" ),
      wrapper = $( "<div>", { html: item.label } ); 

    if ( item.disabled ) {
        li.addClass( "ui-state-disabled" );
    }      
    if ( $(item.element).attr('group') == 1 ){
        li.css({'opacity':1});  
        wrapper.css({'font-weight':'bold'});
    }
    if( $(item.element).hasClass('required')) {
        wrapper.addClass('required');  
    }
    if(item.element.attr( 'ui-state-error' )){
        wrapper.addClass('ui-state-error');
    }
    if(item.element.attr( 'hidden' )){
        li.hide();
    }    
    
    let rt_checkbox = item.element.attr( "rt-checkbox" );
    if(rt_checkbox>=0){
        $('<span style="float:left;padding:2px 0;min-width:1.5em;border:1px dot lightblue" '
                + ' data-id="'+item.element.attr( 'data-id' )
                + '" class="rt-checkbox ui-icon ui-icon-check-'+(rt_checkbox==1?'on':'off')+'"></span>')
          .appendTo( wrapper );    
    }
    
    let icon_url = item.element.attr( "icon-url" );
    if(icon_url){
    
        $('<span style="float:left;padding-right:2px"><img alt src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        + '" class="rt-icon" style="background-image: url(&quot;'+icon_url+ '&quot;);"/></span>')
          .prependTo( wrapper );    

    }

    let rt_count = item.element.attr( "rt-count" );
    if(rt_count>0){
        $('<span style="float:right;padding-right:2px">'+rt_count+'</span>')
          .appendTo( wrapper );    
    }

    let entity_id = item.element.attr( 'entity-id' );
    if(entity_id>0){
        $('<span style="font-size:0.7em;font-style:italic;padding-left:1em">id'+entity_id+'</span>')
          .appendTo( wrapper );    
    }
    
    let depth = parseInt($(item.element).attr('depth'));
    if(!(depth>0)) depth = 0;
    wrapper.attr('data-depth', depth);
    if(rt_checkbox>=0) depth = depth + 1;
    wrapper.css('padding-left',(depth+0.2)+'em');
    
    return li.append( wrapper ).appendTo( ul );
  },
  
  
  hideOnMouseLeave: function(parent){
      
        let myTimeoutId = -1;
        
        //show hide function
        let _hide = function(ele) {
            myTimeoutId = setTimeout(function() {
                ele.hSelect('close');
               
                }, 800);
        },
        _show = function(ele, parent) {
            clearTimeout(myTimeoutId);
            
            $('.menu-or-popup').hide(); //hide other
            
           
            
            return false;
        };
      

        this._on( parent, {
            mouseenter : function(){_show(this.element, parent)},
            mouseleave : function(){_hide(this.element)}
        });
        this._on( this.element.hSelect('menuWidget'), {
            mouseenter : function(){_show(this.element, parent)},
            mouseleave : function(){_hide(this.element)}
        });
      
  },
  

  

});

$.fn.sideFollow = function(dtime) {

    let floating = $(this);
    let originalTop =  parseInt(floating.attr('data-top'));
    if(!(originalTop>=0)) originalTop = 180;

    dtime = dtime ?dtime :1000;

    goFollow();

    $(window).on('scroll', function() {
        goFollow();
    });

    function goFollow() {
        let scrollTop = $(this).scrollTop();
        floating.animate({
            top: originalTop + scrollTop
        }, {
            duration: dtime,
            queue: false
        });
    }

}
