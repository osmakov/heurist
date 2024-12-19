/**
*  Utility functions for database structure
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

/*
Selectors:

TERMS

getInverseTermById  - (used in record edit for relmarker fields)

getTermValue - Returns Label and Termcode in brackets (optionally) (used in EN and faceted search)

getTermByCode - returns term by code in given vocab (used in lookup geonames only)

getTermByLabel - returns term ID in vocabulary by label (in record edit for search and duplication check)

getTermVocab - returns vocabulary for given term - real vocabulary (not by reference)

trm_InVocab - returns true if term belongs to vocabulary (including by reference)

isTermByReference - return false if given term belongs to vocabulary, otherwise returns level of reference

getColorFromTermValue - Returns hex color by label or code for term by id (for googlemaps only)

    trm_TreeData  - returns hierarchy for given vocabulary as a flat array, recordset or tree data
    trm_HasChildren - is given term has children
    trm_getVocabs - get all vocabularies OR for given domain
    trm_getAllVocabs - get all vocab where given term presents directly or by reference
    trm_RemoveLinks - remove all entries of term from trm_Links

    
WORKFLOW STAGES

getSwfByRectype - returns rules for recordtype and current user 

RECTYPES
   

createRectypeStructureTree
getLinkedRecordTypes  -  FIX in search_faceted.js

hasFields - returns true if rectype has a field in its structure
rstField - Returns rectype header or details field values


    getLocalID
    getConceptID

getTrashGroupId

getHierarchyTitles - returns list of rt and dt titles for linked hierachy rt:dt:rt:dt
                    (in faceted search and linked geo places)
*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}

//init only once
if (!window.hWin.HEURIST4.dbs) 
{

window.hWin.HEURIST4.dbs = {
    
    baseFieldType: {
            enum: 'Terms list',
            float: 'Numeric',
            date: 'Date / temporal',
            file: 'File',
            geo: 'Geospatial',
            freetext: 'Text (single line)',
            blocktext: 'Memo (multi-line)',
            resource: 'Record pointer',
            relmarker: 'Relationship marker',
            separator: 'Heading (no data)',
            //calculated: 'Calculated',
            // Note=> the following types are no longer deinable but may be required for backward compatibility
            relationtype: 'Relationship type',
            integer: 'Numeric - integer',
            year: 'Year (no mm-dd)',
            boolean: 'Boolean (T/F)'},
    
    
    needUpdateRtyCount: -1,
    
    rtg_trash_id: 0,
    dtg_trash_id: 0,
    vcg_trash_id: 0,
    
    vocabs_already_synched: false, //set to true after first direct import by mapping to avoid sync on every request

    /** 
     * @function getTermVocab
     * return vocabulary for given term - real vocabulary (not by reference)
     * @param {number} trm_ID - Term ID
     * @returns {number} trm_ID - Vocab ID
     * 
    */
    getTermVocab: function(trm_ID){
        let trm_ParentTermID;
        do{
            trm_ParentTermID = $Db.trm(trm_ID, 'trm_ParentTermID');
            if(trm_ParentTermID>0){
                trm_ID = trm_ParentTermID;
            }else{
                break;
            }
        }while (trm_ParentTermID>0);
        
        return trm_ID;        
    },

    /** 
     * @function isTermByReference
     * @param {number} vocab_id - Vocab ID
     * @param {number} trm_ID - Term ID
     * @returns  {(false|number)} false if given term belongs to vocabulary, otherwise number for a level of reference 
     * (0 - first level - parent is either vocab or "real" terms, 1 or more - parent is term by reference also)
     * 
    */
    isTermByReference: function(vocab_id, trm_ID){
        
        let real_vocab_id = $Db.getTermVocab(trm_ID);
        
        if(real_vocab_id==vocab_id){
            return false; //this is not reference
        }
        
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 

        /**
         * @function __checkParents
         * @param {number} recID 
         * @param {number} lvl 
         * @returns {(number|boolean)}  number if term has children, false if term has not 
         */
        
        function __checkParents(recID, lvl){
            
            let children = t_idx[recID]; //array of children ids trm_Links (including references)    
            if(children){
                let k = window.hWin.HEURIST4.util.findArrayIndex(trm_ID, children);
                
                if(k>=0){
                    return lvl;
                }
                
                for(k=0;k<children.length;k++){
                    
                    let real_parent_id = $Db.trm(children[k], 'trm_ParentTermID');
                    let lvl2 = lvl + (lvl>0 || (real_parent_id>0 && real_parent_id!=recID))?1:0;
                    
                    let res = __checkParents(children[k], lvl2);
                    if(res!==false) return res;
                }
            }
            return false;
        }    
        
        return __checkParents(vocab_id, 0);
    },
    

    /**
    *  Returns Label and Termcode in brackets (optionally)
     * @function getTermvalue
     * Returns label and code for term by id
     * @param {number} termID 
     * @param {number} withcode 
     * @returns {string} Term name and its code if any
     */
    getTermValue: function(termID, withcode){
        
        let term = $Db.trm(termID);
        let termName, termCode='';

        if(term){
            termName = term['trm_Label'];
            termCode = term['trm_Code'];
            if(window.hWin.HEURIST4.util.isempty(termCode)){
                termCode = '';
            }else{
                termCode = " ("+termCode+")";
            }
        } else {
            termName = 'not found term#'+termID;
        }

        return termName+(withcode ?termCode :'');
    },
    
    /**
     * Returns empty string if term is not found. trm_InverseTermID if inverse term is found or termID if it is not  
     * (used in record edit for relmarker fields)
     * 
     * @function getInverseTermById
     * get inverse term id
     * @param {number} termID 
     * @returns {string|number} 
     */
    
    getInverseTermById: function(termID){
        let term = $Db.trm(termID);
        if(term){
            let invTermID = term['trm_InverseTermID'];
            if(invTermID>0) return invTermID;
            return termID;
        }
        return '';
    },
    
    /**
    *  Converts term code or label for hex color - used in recordset.toTimemap only
    *  (for google maps)
    * 
    * @function getColorFromTermValue
    * Returns hex color by label or code for term by id
    * @param {number} termID 
    * @returns {string}
    */
    getColorFromTermValue: function(termID){

        let termName, termCode='';

        if(termID>0){
            let term = $Db.trm(termID);
            if(term){

                termName = term['trm_Label'];
                termCode = term['trm_Code'];
                if(window.hWin.HEURIST4.util.isempty(termCode)){
                    let cnames = window.hWin.HEURIST4.ui.getColorArr('names');
                    let idx = window.hWin.HEURIST4.util.findArrayIndex(termName.toLowerCase(),cnames);
                    if(idx>=0){
                        cnames = window.hWin.HEURIST4.ui.getColorArr('hexs');
                        termCode = '#'+cnames[idx]; 
                    }
                }
            }
        }

        return termCode;
    },

    //========================================================================
    

    /**
     * @function createRectypeStructureTree
     * returns rectype structure as treeview data
     * there is similar method on server side - however on client side it is faster
     * used for treeview in import structure, faceted search wizard
     * todo - use it in smarty editor and title mask editor
     * parentcode - prefix for code    
      
     * @param {Object} db_structure // not used in this function, is it usefull to keep it here? 
     * @param {number} $mode - $mode 
     *    3 - for record title mask editor - without reverse, enum (id,label,code,internal id) - max levels depth is calculated
     *    4 - find reverse links and relations   
     *    5 - for faceted search wiz, filter builder - lazy treeview with reverse links
     *    6 - for import structure, export csv - lazy tree without reverse
     *    7 - for smarty - lazy tree without reverse, with relationship stub and enum (id,label,code,internal id)
     * @param {Array} rectypeids - set of rty ids 
     * @param {Array} fieldtypes - array of fieldtypes, 
     *               all
     *               header - only title and modified fields
     *               header_ext - all header fields
     *               parent_link - include field DT_PARENT_ENTITY - link to parent record
     *       header - all+header fields
     * @param {string} parentcode  - prefix for code
     * @param {string} field_order - field ordering
     *    0 | null - Record structure order
     *    1 - Alphabetic Order
     * @returns {Array} res 
     */
    createRectypeStructureTree: function( db_structure, $mode, rectypeids, fieldtypes, parentcode, field_order ) {
        
        let options = {db_structure:db_structure, mode: $mode, rectypeids:rectypeids, fieldtypes:fieldtypes, parentcode:parentcode, field_order:field_order};
    
        return window.hWin.HEURIST4.dbs.createRectypeStructureTree_new( options );
    },

    createRectypeStructureTree_new: function( options )
    {
        let $mode = options.mode,
            rectypeids = options.rectypeids,
            fieldtypes = options.fieldtypes,
            parentcode = options.parentcode,
            field_order = options.field_order,
            enum_mode = options.enum_mode; 
        
        if($mode==3 || $mode==7){
            enum_mode = 'expanded'
        }
        
        
        const DT_PARENT_ENTITY  = window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY'];
        
        let rst_links = $Db.rst_links();
        
        let _separator = ($mode==3)?'..':':';
        
        let recTypesWithParentLink = [];
        
        let max_allowed_depth = 2;
        
        //-------------------- internal functions    

    /**
     * @function __getRecordTypeTree
     * @param {number} $recTypeId 
     * @param {number} $recursion_depth 
     * @param {number} $mode 
     * @param {Array} $fieldtypes 
     * @param {Array} $pointer_fields 
     * @param {boolean} $is_parent_relmarker 
     * @returns {{key: number, title: string,type: string, conceptCode:number, rtyID_local: number, code: string, children: Array} Object}
     */    
        

    function __getRecordTypeTree($recTypeId, $recursion_depth, $mode, $fieldtypes, $pointer_fields, $is_parent_relmarker, is_multi_constrained){
            
            let $res = {};
            let $children = [];
            let $dtl_fields = [];
            
            //add default fields - RECORD TYPE HEADER
            if($mode==3){

                $children.push({key:'rec_ID',title:'Record ID', code:'Record ID'});
                $children.push({key:'rec_RecTypeID', title:'Record TypeID', code:'Record TypeID'});
                $children.push({key:'rec_TypeName', title:'Record TypeName', code:'Record TypeName'});
                $children.push({key:'rec_Modified', title:"Record Modified", code:'Record Modified'});

                $children = [
                    {title:'<span style="font-style:italic">metadata</span>', folder:true, is_generic_fields:true, children:$children}];

                if($recursion_depth>0){ // keep record title separate from generic fields
                    $children.unshift({key:'rec_Title', type:'freetext', title:'Constructed title', code:'Record Title'});
                }
            }else
            if($recursion_depth==0 && $fieldtypes.length>0){    
                
                //include record header fields
                let all_header_fields = $fieldtypes.indexOf('header_ext')>=0;
                if($fieldtypes.indexOf('header')>=0){
                    $fieldtypes.push('title');
                    $fieldtypes.push('modified');
                }  
                
                let recTitle_item = null;
                
                if(all_header_fields || $fieldtypes.indexOf('ID')>=0 || $fieldtypes.indexOf('rec_ID')>=0){
                    $children.push({key:'rec_ID', type:'integer',
                        title:('ID'+($mode!=7?' <span style="font-size:0.7em">(Integer)</span>':'')), 
                        code:($recTypeId+_separator+'ids'), name:'Record ID'});
                }

                if(all_header_fields || $fieldtypes.indexOf('title')>=0 || $fieldtypes.indexOf('rec_Title')>=0){
                   
                    recTitle_item = {key:'rec_Title', type:'freetext',
                        title:('Title'+($mode!=7?' <span style="font-size:0.7em">(Constructed Text)</span>':'')), 
                        code:($recTypeId+_separator+'title'), name:'Record title'};
                }
                
                if(all_header_fields || $fieldtypes.indexOf('typeid')>=0 || $fieldtypes.indexOf('rec_RecTypeID')>=0){
                    $children.push({key:'rec_RecTypeID', 
                        title:('Record TypeID'+($mode!=7?' <span style="font-size:0.7em">(Integer)</span>':'')), 
                        code:$recTypeId+_separator+'typeid', name: 'Record type ID'});
                }
                if(all_header_fields || $fieldtypes.indexOf('typename')>=0 || $fieldtypes.indexOf('rec_TypeName')>=0){
                    $children.push({key:'rec_TypeName', 
                        title:('Record TypeName'+($mode!=7?' <span style="font-size:0.7em">(Text)</span>':'')), 
                        code:$recTypeId+_separator+'typename', name: 'Record type'});
                }
                
                if(all_header_fields || $fieldtypes.indexOf('added')>=0 || $fieldtypes.indexOf('rec_Added')>=0){
                    $children.push({key:'rec_Modified', type:'date',
                        title:('Added'+($mode!=7?' <span style="font-size:0.7em">(Date)</span>':'')), 
                        code:($recTypeId+_separator+'added'), name:'Date added'});
                }
                if(all_header_fields || $fieldtypes.indexOf('modified')>=0 || $fieldtypes.indexOf('rec_Modified')>=0){
                    $children.push({key:'rec_Modified', type:'date',
                        title:('Modified'+($mode!=7?' <span style="font-size:0.7em">(Date)</span>':'')), 
                        code:($recTypeId+_separator+'modified'), name:'Date modified'});
                }
                if(all_header_fields || $fieldtypes.indexOf('addedby')>=0 || $fieldtypes.indexOf('rec_AddedBy')>=0){
                    $children.push({key:'rec_AddedBy', type:'enum',
                        title:('Creator'+($mode!=7?' <span style="font-size:0.7em">(User)</span>':'')), 
                        code:($recTypeId+_separator+'addedby'), name:'Creator (user)'});
                }
                if(all_header_fields || $fieldtypes.indexOf('url')>=0 || $fieldtypes.indexOf('rec_URL')>=0){
                    $children.push({key:'rec_URL', type:'freetext',
                        title:('URL'+($mode!=7?' <span style="font-size:0.7em">(Text)</span>':'')), 
                        code:($recTypeId+_separator+'url'), name:'Record URL'});
                }
                
                if(all_header_fields || $fieldtypes.indexOf('notes')>=0 || $fieldtypes.indexOf('rec_ScratchPad')>=0){
                    $children.push({key:'rec_ScratchPad', type:'freetext',
                        title:('Notes'+($mode!=7?' <span style="font-size:0.7em">(Text)</span>':'')), 
                        code:($recTypeId+_separator+'notes'), name:'Record Notes'});
                }
                if(all_header_fields || $fieldtypes.indexOf('owner')>=0 || $fieldtypes.indexOf('rec_OwnerUGrpID')>=0){
                    $children.push({key:'rec_OwnerUGrpID', type:'enum',
                        title:('Owner'+($mode!=7?' <span style="font-size:0.7em">(User or Group)</span>':'')), 
                        code:($recTypeId+_separator+'owner'), name:'Record Owner'});
                }
                if(all_header_fields || $fieldtypes.indexOf('visibility')>=0 || $fieldtypes.indexOf('rec_NonOwnerVisibility')>=0){
                    $children.push({key:'rec_NonOwnerVisibility', type:'enum',
                        title:('Visibility'+($mode!=7?' <span style="font-size:0.7em">(Terms)</span>':'')), 
                        code:($recTypeId+_separator+'access'), name:'Record Visibility'});
                }

                if(all_header_fields || $fieldtypes.indexOf('tags')>=0 || $fieldtypes.indexOf('rec_Tags')>=0){
                    $children.push({key:'rec_Tags', type:'terms',
                        title:('Tags'+($mode!=7?' <span style="font-size:0.7em">(Terms)</span>':'')), 
                        code:($recTypeId+_separator+'tag'), name:'Record Tags'});
                }
                
                if(all_header_fields || $mode == 7){
                    let $grouped = [];
                    
                    if($is_parent_relmarker){
                        let rt_id = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'];
                        let dc = window.hWin.HAPI4.sysinfo['dbconst'];
                        
                        let $rl_children = [];
                        
                        let $details = $Db.rst(rt_id);
                        $details.each2(function($dtID, $dtValue){
                            
                            let $dt_type = $Db.dty($dtID,'dty_Type');
                            if( $dtValue['rst_RequirementType']=='forbidden' ||
                                $dt_type == 'separator' ||
                                $dtID == dc['DT_TARGET_RESOURCE'] ||
                                $dtID == dc['DT_PRIMARY_RESOURCE'] 
                                ){
                                return;    
                            }
                            
                            if($dtID == dc['DT_RELATION_TYPE']){
                                $dt_type = 'reltype';
                            }    
                            
                            let titleR = $dtValue['rst_DisplayName'];
                            if(titleR.indexOf('Relationship ')<0){
                                titleR = 'Relationship '+titleR;
                            }
                                
                            $rl_children.push({type:$dt_type,
                                title: titleR, 
                                code:(rt_id+_separator+'r.'+$dtID), name:$dtValue['rst_DisplayName']});
                            
                        });
/*                        
                        $rl_children.push({type:'reltype',
                            title:'Relationship type', 
                            code:(rt_id+_separator+'r.'+dc['DT_RELATION_TYPE']), name:'Relationship type'}); 
                        $rl_children.push({type:'date',
                            title:'Relationship Start Date', 
                            code:(rt_id+_separator+'r.'+dc['DT_START_DATE']), name:'Start Date'});
                            
                        $rl_children.push({type:'date',
                            title:'Relationship End Date', 
                            code:(rt_id+_separator+'r.'+dc['DT_END_DATE']), name:'End Date'});
                        $rl_children.push({type:'freetext',
                            title:'Relationship Name', 
                            code:(rt_id+_separator+'r.'+dc['DT_NAME']), name:'Name'});
                        $rl_children.push({type:'freetext',
                            title:'Relationship Description', 
                            code:(rt_id+_separator+'r.'+dc['DT_SHORT_SUMMARY']), name:'Description'});
                        $rl_children.push({type:'enum',
                            title:'Relationship interpretation reference', 
                            code:(rt_id+_separator+'r.'+dc['DT_INTERPRETATION_REFERENCE']), name:'Interpretation Reference'});
*/                        
                        $grouped.push(
                            {title:'<span style="font-style:italic">Relationship Fields</span>', folder:true, 
                                        is_generic_fields:true, children:$rl_children});
                            
                    }else if($mode==5 && $recTypeId>0 && is_multi_constrained>0){ //for search builder
                        
                        const rty_Name = $Db.rty($recTypeId, 'rty_Name');

                        $grouped.push( {code:`${$recTypeId}:exists`,
                            key: 'exists', 
                            name: rty_Name, 
                            title: `${rty_Name} records ${is_multi_constrained}`, 
                            type: 'freetext'} );
                    }

                    if(recTitle_item){
                        $grouped.push( recTitle_item );
                    }
                    
                    $grouped.push(
                        {title:'<span style="font-style:italic">metadata</span>', folder:true, is_generic_fields:true, children:$children});
                    
                    $children = $grouped;
                }
            }

            if($recTypeId>0 && $Db.rty($recTypeId,'rty_Name')){//---------------

                const rty_Name = $Db.rty($recTypeId,'rty_Name');
            
                $res['key'] = $recTypeId;
                $res['title'] = rty_Name;
                $res['type'] = 'rectype';
                
                $res['conceptCode'] = $Db.getConceptID('rty', $recTypeId);
                $res['rtyID_local'] = $recTypeId; //$Db.getLocalID('rty', $rt_conceptcode); //for import structure
                
                if(($mode<5 || $recursion_depth==0)){


                    let $details = $Db.rst($recTypeId);
                    
                    //
                    if($fieldtypes.indexOf('parent_link')>=0 && !$Db.rst($recTypeId,DT_PARENT_ENTITY)){
                        
                        //find all parent record types that refers to this record type
                        let $parent_Rts = rst_links.parents[$recTypeId];
                        
                        if($parent_Rts && $parent_Rts.length>0){
                        
                            //create fake rectype structure field
                            let $ffr = {};
                            $ffr['rst_DisplayName'] = 'Parent entity';
                            $ffr['rst_PtrFilteredIDs'] = $parent_Rts.join(',');
                           
                            $ffr['rst_DisplayHelpText'] = 'Reverse pointer to parent record';
                            $ffr['rst_RequirementType'] = 'optional';
                            $ffr['rst_DisplayOrder'] = '0'; // place at top
                                  
                            $details.addRecord(DT_PARENT_ENTITY, $ffr)
                            
                            recTypesWithParentLink.push($recTypeId);
                        }
                    }
                    
                    let $children_links = [];
                    let $new_pointer_fields = [];
                    
                    // add details --------------------------------
                    if($details){
                        
                    //count number of relmarkers and define allowed max depth for rectitle mask tree
                    if($recursion_depth==0 && ($mode==3 || $mode==4)){
                        let cnt_pointers = 0;
                        $details.each2(function($dtID, $dtValue){
                            if($dtValue['rst_RequirementType']!='forbidden'){
                                let $dt_type = $Db.dty($dtID,'dty_Type');
                                if($dt_type=='relmarker'){
                                      cnt_pointers++;
                                }
                            }
                        });
                        max_allowed_depth = (cnt_pointers>10)?2:3;
                    }

                    $details.each2(function($dtID, $dtValue){
                        //@TODO forbidden for import????
                        if($dtValue['rst_RequirementType']!='forbidden'){

                            let $dt_type = $Db.dty($dtID,'dty_Type');
                            
                            if($dt_type=='resource' || $dt_type=='relmarker'){ //title mask w/o relations
                                    $new_pointer_fields.push( $dtID );
                            }

                            let $res_dt = __getDetailSection($recTypeId, $dtID, $recursion_depth, $mode, 
                                                                    $fieldtypes, null, $new_pointer_fields);
                            if($res_dt){
                                
                                if($res_dt['type']=='resource' || $res_dt['type']=='relmarker'){

                                    if($mode==3 && $res_dt['constraint'] && $res_dt['constraint']>1){ 
                                        //for rectitle mask do not create additional level for  multiconstrained link

                                        let separate_meta_fields = $res_dt['constraint'] > 1;
                                        for (let i=0; i<$res_dt['constraint']; i++){
                                            $res_dt['children'][i]['code'] = '{'+$res_dt['children'][i]['title'] +'}'; // change code

                                            if(separate_meta_fields){
                                                // remove constructed title and metadata, keep fields node
                                               

                                                // move fields out of sub-heading
                                                let fields = $res_dt['children'][i]['children'].pop();
                                                $res_dt['children'][i]['children'] = fields['children'];
                                            }
                                        }


                                        if(separate_meta_fields){ // if more than one rectype, place constrcuted title and metadata outside

                                            let meta_fields = [
                                                {key:'rec_ID',title:'Record ID', code:'Record ID'}, {key:'rec_RecTypeID', title:'Record TypeID', code:'Record TypeID'},
                                                {key:'rec_TypeName', title:'Record TypeName', code:'Record TypeName'}, {key:'rec_Modified', title:"Record Modified", code:'Record Modified'}
                                            ];
                                            let meta_title = '<span style="font-style:italic">metadata</span>';

                                            $res_dt['children'].unshift(
                                                {key:'rec_Title', type:'freetext', title:'Constructed title', code:'Record Title'}, {title:meta_title, folder:true, is_generic_fields:true, children:meta_fields}
                                            );
                                        }
                                    }

                                    $children_links.push($res_dt);
                                }else{
									
                                    if($res_dt['type']=='enum' && $mode==3){
                                        $res_dt['title'] = "<span class='ui-icon ui-icon-menu' style='margin-right:2px;'>&nbsp;</span>" + $res_dt['title'];
                                    }
									
                                    $dtl_fields.push($res_dt);
                                }
                            }
                        }
                    });//for details

                    }
                    
                    //add record pointer and relation at the end of result array
                    $dtl_fields = $dtl_fields.concat($children_links);

                    $dtl_fields.sort(function(a,b){
                        if(field_order == 1){
                            let nameA = a['name'].toLocaleUpperCase();
                            let nameB = b['name'].toLocaleUpperCase();
                            return nameA.localeCompare(nameB);
                        }else{
                            return (a['display_order']<b['display_order'])?-1:1;
                        }
                    });

                    if($fieldtypes.indexOf('anyfield')>=0){ //for filter builder 
                        $dtl_fields.unshift({key:'anyfield', type:'freetext',
                        title:"<span style='font-size:0.9em;font-style:italic;'>ANY</span>", 
                        code:($recTypeId+_separator+'anyfield'), name:'Any field'});    
                    }

                    //--------------------------------------------
                    //find all reverse links and relations
                    if( ($mode==4 && $recursion_depth<2) || ($mode==5 && $recursion_depth==0) )
                    {
                        let rev_fields = {};
                        let reverse_fields = rst_links.reverse[$recTypeId]; //all:, dty_ID:[rty_ID,...]
                        let twice_only = 0;
                        while(twice_only<2){

                            for (let $dtID in reverse_fields) {
                                if($dtID>0 && 
                                    ( $pointer_fields==null ||    // to avoid recursion
                                        (Array.isArray($pointer_fields) &&   
                                        window.hWin.HEURIST4.util.findArrayIndex($dtID, $pointer_fields)<0) ) )
                                {
                                    rev_fields[$dtID] = reverse_fields[$dtID];
                                }
                            }
                            reverse_fields = rst_links.rel_reverse[$recTypeId]; //all:, dty_ID:[rty_ID,...]
                            twice_only++;
                        }
                        
                        for (let $dtID in rev_fields) {
                                let $rtyIDs = rev_fields[$dtID];
                                for(let i=0; i<$rtyIDs.length; i++)  {
                                    const $res_dt = __getDetailSection($rtyIDs[i], $dtID, $recursion_depth, $mode, $fieldtypes, $recTypeId, null);
                     
                                    if($res_dt){
                                        $dtl_fields.push( $res_dt );
                                    }
                                }
                        }//for
                    }
                    
                    
                }
                
                if($mode==7 && $recursion_depth==0 && !parentcode){
                    $dtl_fields.push(__getRecordTypeTree('Relationship', 0, $mode, $fieldtypes, null));
                }   

            }
            else if($recTypeId=='Relationship') { //----------------------------

                $res['title'] = 'Relationship';
                $res['type'] = 'relationship';
                $res['code'] = 'Relationship';

                //add specific Relationship fields
                $children.push({code:'recRelationType', title:'Relation Type'});
                $children.push({code:'recRelationNotes', title:'Relation Notes'});
                $children.push({code:'recRelationStartDate', title:'Relation StartDate'});
                $children.push({code:'recRelationEndDate', title:'Relation EndDate'});

                if($mode == 7){

                    let skip = [
                        window.hWin.HAPI4.sysinfo.dbconst.DT_PRIMARY_RESOURCE, window.hWin.HAPI4.sysinfo.dbconst.DT_TARGET_RESOURCE,
                        window.hWin.HAPI4.sysinfo.dbconst.DT_RELATION_TYPE, window.hWin.HAPI4.sysinfo.dbconst.DT_SHORT_SUMMARY,
                        window.hWin.HAPI4.sysinfo.dbconst.DT_START_DATE, window.hWin.HAPI4.sysinfo.dbconst.DT_END_DATE
                    ];
                    $Db.rst(window.hWin.HAPI4.sysinfo.dbconst.RT_RELATION).each2((dty_ID, rst_Fields) => {

                        if(skip.indexOf(dty_ID) >= 0){
                            return;
                        }

                        $children.push({code: dty_ID, title: `Relation ${rst_Fields.rst_DisplayName}`})
                    });
                }

                $res['children'] = $children;
                
            }else if($mode==5 || $mode==6) //----------------------------------- for query builder and facet search tree
            {
                //record type is array - add common fields only
                
                $res['title'] = 'Any record type';
                $res['type'] = 'rectype';
                /* disabled
                if(false && $mode==5 && $recursion_depth==0 && $recTypeId && $recTypeId.indexOf(',')>0){ //for faceted search
                    $res['key'] = $recTypeId;
                    $res['type'] = 'rectype';
                    
                    var recTypes = $recTypeId.split(',');
                    
                    $res['title'] = $Db.rty( recTypes[0],'rty_Name');
                    
                    var  $details = $Db.rst(recTypes[0]); 
                     
                    var $children_links = [];
                    var $new_pointer_fields = [];

                    $details.each2(function($dtID, $dtValue){
                        
                        if($dtValue['rst_RequirementType']!='forbidden'){

                            var $dt_type = $Db.dty($dtID,'dty_Type');
                            if($dt_type=='resource' || $dt_type=='relmarker'){
                                    $new_pointer_fields.push( $dtID );
                            }
                            
                            $res_dt = __getDetailSection(recTypes[0], $dtID, $recursion_depth, $mode, 
                                                                    $fieldtypes, null, $new_pointer_fields);
                            if($res_dt){
                                
                                var codes = $res_dt['code'].split(_separator);
                                codes[0] = $recTypeId;
                                $res_dt['code'] = codes.join(_separator);
                                
                                if($res_dt['type']=='resource' || $res_dt['type']=='relmarker'){
                                    $children_links.push($res_dt);
                                }else{
                                    $children.push($res_dt);
                                }
                            }
                        }
                    });//for details
                    
                    //sort bt rst_DisplayOrder
                    $children.sort(function(a,b){
                        return (a['display_order']<b['display_order'])?-1:1;
                    });
                    
                    //add record pointer and relation at the end of result array
                    $children = $children.concat($children_links);                    
                    
                }*/
                
            }

            if($dtl_fields.length > 0){
                if($children.length==0 && $mode==6){
                    //no header fields - avoid 
                    $children = $dtl_fields;
                }else{
                    $children.push({title: 'fields', folder: true, expanded:(!parentcode), is_rec_fields: true, children: $dtl_fields});    
                }
            }

            if($mode<5 || $recursion_depth==0){
                $res['children'] = $children;
            }

            return $res;
    } //__getRecordTypeTree

    /*
    $dtValue - record type structure definition
    returns display name  or if enum array
    $mode - 3 all, 4, 5 for treeview (5 lazy) , 6 - for import csv(dependencies)
    */

    /**
     * @function __getDetailSection 
     * @param {number} $recTypeId 
     * @param {number} $dtID  - detail type ID
     * @param {number} $recursion_depth 
     * @param {number} $mode 
     * @param {Array} $fieldtypes 
     * @param {number} $reverseRecTypeId 
     * @param {Array} $pointer_fields 
     * @returns {null|Array} 
     * 
     */
    function __getDetailSection($recTypeId, $dtID, $recursion_depth, $mode, $fieldtypes, $reverseRecTypeId, $pointer_fields){

        let $res = null;

        let $dtValue = $Db.rst($recTypeId, $dtID);

        let $detailType = $Db.dty($dtID,'dty_Type');
        
        if(($mode==7) && $detailType=='relmarker'){ //$mode==3 || 
            return null;   
        }
        
        let $dt_label   = $dtValue['rst_DisplayName'];
        let $dt_title   = $dtValue['rst_DisplayName'];
        let $dt_conceptcode   = $Db.getConceptID('dty', $dtID);
        let $dt_display_order = $dtValue['rst_DisplayOrder'];
        
        let $pointerRecTypeId = ($dtID==DT_PARENT_ENTITY)?$dtValue['rst_PtrFilteredIDs']:$Db.dty($dtID,'dty_PtrTargetRectypeIDs');
        if(window.hWin.HEURIST4.util.isnull($pointerRecTypeId)) $pointerRecTypeId = '';
        
        let $pref = "";
        
        if ($fieldtypes.indexOf('all')>=0   //($mode==3) || 
            || window.hWin.HEURIST4.util.findArrayIndex($detailType, $fieldtypes)>=0) //$fieldtypes - allowed types
        {

        switch ($detailType) {
            case 'separator':
                $res = {};
                $res['checkbox'] = false;
                if($dt_label == '-'){
                    $dt_title = '<span style="display: inline-block; width: 150px;"><hr></span>'; //replace empty header w/ line
                }else{
                    $dt_title = '<span style="font-weight:bold">' + $dt_title + '</span>';
                }
                break;
            case 'enum':
            case 'relationtype':

                $res = {};
                
                if(enum_mode=='expanded'){
                    $res['children'] = [
                        {key:'term',title: 'Term',code: 'term'}, //label
                        {key:'code',title: 'Code',code: 'code'},       
                        {key:'conceptid',title: 'Concept ID',code: 'conceptid'},       
                        {key:'desc',title: 'Description',code: 'desc'},       
                        {key:'internalid',title: 'Internal ID',code: 'internalid'}
                    ];
                    
                    //title mask (3)
                    if($mode==3){

                        $res['children'][2]['title'] = 'Con-ID';
                        $res['children'][4]['title'] = 'Int-ID';

                        $res['children'].splice(3,1); // remove description option
                    }
                }
                
                
                break;

            case 'resource': // link to another record type
            case 'relmarker':
            
                
                if ($mode==4 || $mode==3){ //record titlemask
                   //max_allowed_depth = 3; calculated
                }else if ($mode==5 || $mode==6 || $mode==7) //make it 1 for lazy load
                   max_allowed_depth = 1; 
                                                                
                if($recursion_depth<max_allowed_depth){
                    
                    if($reverseRecTypeId!=null){
                            $res = __getRecordTypeTree($recTypeId, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                            if($res){
                                $res['rt_ids'] = $recTypeId; //list of rectype - constraint
                               
                                $pref = ($detailType=="resource")?"lf":"rf";

                                $dt_title = "<span>&lt;&lt; <span style='font-weight:bold'>" 
                                        + $Db.rty($recTypeId, 'rty_Name') + "</span> . " + $dt_title + '</span>';
                                
                                if($mode==5 || $mode==6){
                                    $res['lazy'] = true;
                                }

                                let parents = $Db.rst_links().parents[$reverseRecTypeId];
                                if(parents && parents.includes($recTypeId) !== false){
                                    $res['isparent'] = 1;
                                    $res['rst_DisplayOrder'] = '0'; // place at top
                                }

                                $res['isreverse'] = 1;
                            }
                    }
                    else{

                            $pref = ($detailType=="resource")?"lt":"rt";

                            let $is_required = ($dtValue['rst_RequirementType']=='required');
                            let $rectype_ids = $pointerRecTypeId.split(",");
                             
                            if($mode==4 || $mode==5 || $mode==6){
                                
                                let $type_name = $Db.baseFieldType[$detailType];
                                
                                $dt_title = ' <span'+($mode!=5?' style="font-style:italic"':'')
                                    +'>' + $dt_title 
                                    +'</span> <span style="font-size:0.7em">(' + $type_name + ')</span>';
                            }else{
                                $dt_title = ' <span style="font-style:italic">' + $dt_title + '</span>';
                            }

                            $res = {};                            
                            
                            if($pointerRecTypeId=="" || $rectype_ids.length==0){ //unconstrainded
                                                    //
                               
                                if($mode==5){
                                    $res['rt_ids'] = '';
                                    $res['lazy'] = true;
                                }else{
                                    $res = __getRecordTypeTree( null, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                                }

                            }else{ //constrained pointer

                                if($rectype_ids.length>1){
                                    $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                    $res['constraint'] = $rectype_ids.length;
                                    if($mode<5) $res['children'] = [];
                                }
                                if($mode==5 || $mode==6 || $mode==7){ 
                                    $res['rt_ids'] = $pointerRecTypeId;
                                    $res['lazy'] = true;
                                    
                                }else{
                                
                                    for (let k in $rectype_ids){
                                        const $rtID = $rectype_ids[k];
                                        const $rt_res = __getRecordTypeTree($rtID, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                                        if($rectype_ids.length==1){//exact one rectype constraint
                                            //avoid redundant level in tree
                                            $res = $rt_res;
                                            $res['constraint'] = 1;
                                            $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                        }else if($rt_res!=null){
                                            
                                            $res['children'].push($rt_res);
                                            $res['constraint'] = $rectype_ids.length;
                                            
                                        }else{
                                            $res['constraint'] = null;
                                            $res['children'].push({
                                                title:'Unconstrained pointer; constrain to record type to see field', 
                                                code:null});
                                        }
                                    }
                                
                                }
                            
                            }
                            
                            $res['required'] = $is_required;
                    }
                }

                break;

            default:
                    $res = {};
        }//end switch
        }

        if($res!=null){

            if(window.hWin.HEURIST4.util.isnull($res['code'])){
              
              if($mode==3){
                  $res['code'] = $dt_label;
              }else{
                  $res['code'] = (($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId)+_separator+$pref+$dtID;  //(($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId)  
              }  
                
            } 
            $res['key'] = "f:"+$dtID;
            if($mode==4 || $mode==5 || $mode==6){
                    
                let $stype = ($detailType=='resource' || $detailType=='relmarker' || $detailType=='separator')?'':$Db.baseFieldType[$detailType];
                if($reverseRecTypeId!=null){
                   
                    $res['isreverse'] = 1;
                }
                if($stype!=''){
                    $stype = " <span style='font-size:0.7em'>(" + $stype + ")</span>";   
                }
                
                $res['title'] = $dt_title + $stype;
                //$res['code'] = 
            }else{
                $res['title'] = $dt_title;    
            }
            $res['type'] = $detailType;
            $res['name'] = $dt_label;
            
            $res['display_order'] = $dt_display_order;
            
            $res['conceptCode'] = $dt_conceptcode;
            $res['dtyID_local'] = $dtID; //$Db.getLocalID('dty', $dt_conceptcode); for import
        }            
        return $res;
        
        
    }
    
    
    /**
     * @function __assignCodes
     * add parent code to children
     * @param {Array} $def 
     * @returns {Array}
     */
    function __assignCodes($def){
        
        for(let $idx in $def['children']){
            const $det = $def['children'][$idx];
            if(!window.hWin.HEURIST4.util.isnull($def['code'])){

                if(!window.hWin.HEURIST4.util.isnull($det['code'])){
                    $def['children'][$idx]['code'] = $def['code'] + _separator + $det['code']; 
                }else{
                    $def['children'][$idx]['code'] = $def['code'];    
                }
            }
            if(Array.isArray($det['children'])){
                   $def['children'][$idx] = __assignCodes($def['children'][$idx]);
            }
        }
        return $def;
    }
    //========================= end internal 

        if(fieldtypes==null){
            fieldtypes = ['integer','date','freetext','year','float','enum','resource','relmarker','relationtype','separator'];
        }else if(!Array.isArray(fieldtypes) && fieldtypes!='all'){
            fieldtypes = fieldtypes.split(',');
        }

        let res = [];

        rectypeids = (!Array.isArray(rectypeids)?rectypeids.split(','):rectypeids);    

        let is_multi_constrained = parentcode?rectypeids?.length:0;
        let pointer_field_id = null;
            
        let is_parent_relmarker = false;
        if(parentcode!=null){
            let codes = parentcode.split(_separator);
            if(codes.length>0){
                let lastcode = codes[codes.length-1];
                is_parent_relmarker = (lastcode.indexOf('rt')==0 || lastcode.indexOf('rf')==0);
                
                if(lastcode.indexOf('lt')==0 && is_multi_constrained==1){
                   pointer_field_id =  lastcode.substr(2); 
                }else{
                   is_multi_constrained = 0;
                }
            }
        }
        
        //create hierarchy tree 
        for (let k=0; k<rectypeids.length; k++) {
            let rectypeID = rectypeids[k];
            
            let def = __getRecordTypeTree(rectypeID, 0, $mode, fieldtypes, null, is_parent_relmarker, is_multi_constrained);
            
                if(def!==null) {
                    if(parentcode!=null){
                        
                        /*if(pointer_field_id && def['code']==''){
                            //special case: search existance or count for single constrained pointer
                            let codes = parentcode.split(_separator);
                            codes[codes.length-1] = pointer_field_id; 
                            def['code'] = codes.join(_separator);
                        }else   */
                        if(def['code']){
                            def['code'] = parentcode+_separator+def['code'];
                        }else{
                            def['code'] = parentcode;
                        }
                    }
                    //asign codes
                    if(Array.isArray(def['children'])){
                       
                        def = __assignCodes(def);
                        res.push( def );
                    }                    
                }
        }

        for (let i=0; i<recTypesWithParentLink.length; i++){
            let $details = $Db.rst(recTypesWithParentLink[i]);
            $details.removeRecord(DT_PARENT_ENTITY); //remove fake parent link    
        }
        
        if(rectypeids.length==1 && $mode==3){
            res = res[0]['children'];            
        }

        return res;
    },
    
    
    /**
     * @todo - IT USES OLD STRUCTURE FORMAT!!! REWRITE asap!!!
     * @function getLinkedRecordTypes
     * use in search_faceted.js 
     * returns array of record types that are resources for given record type
     * {'linkedto':[],'relatedto':[]}
     * need_separate - returns separate array for linked and related
     * @param {number} $rt_ID 
     * @param {Object} db_structure 
     * @param {Array} need_separate 
     * @returns {Array}
     */
    getLinkedRecordTypes: function ($rt_ID, db_structure, need_separate){
        
        if(!db_structure){
            db_structure = window.hWin.HEURIST4;
        }
        
        let $dbs_rtStructs = db_structure.rectypes;
        //find all DIREreverse links (pointers and relation that point to selected rt_ID)
        let $alldetails = $dbs_rtStructs['typedefs'];
        let $fi_type = $alldetails['dtFieldNamesToIndex']['dty_Type'];
        let $fi_rectypes = $alldetails['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        
        let $arr_rectypes = [];
        let res = {'linkedto':[],'relatedto':[]};
        
        let $details = $dbs_rtStructs['typedefs'][$rt_ID]['dtFields'];
        if($details) {
            for (let $dtID in $details) {
                
                let $dtValue = $details[$dtID];
        
                if(($dtValue[$fi_type]=='resource' || $dtValue[$fi_type]=='relmarker')){

                        //find constraints
                        let $constraints = $dtValue[$fi_rectypes];
                        if(!window.hWin.HEURIST4.util.isempty($constraints)){
                            $constraints = $constraints.split(",");
                            //verify record type exists
                            if($constraints.length>0){
                                for (let i=0; i<$constraints.length; i++) {
                                    let $recTypeId = $constraints[i];
                                    if( !$arr_rectypes[$recTypeId] && 
                                        $dbs_rtStructs['typedefs'][$recTypeId]){
                                            
                                            $arr_rectypes.push( $recTypeId );
                                            
                                            if(need_separate){
                                                let t1 = ($dtValue[$fi_type]=='resource')?'linkedto':'relatedto';
                                                res[t1].push( $recTypeId );
                                            }
                                    }
                                }                            
                            } 
                        }
                }
            }
        }
        
        return  need_separate ?res :$arr_rectypes;
        
    },

    /**
     * @function getLinkedRecordTypes_cache
     * Get list of record types linked and related, to or from, the provided record type
     * @param {int} rty_ID record type ID
     * @param {boolean} need_separate separate linkedto and relatedto 
     * @param {string} direction {to,from,both} which direction to get, to the record type, from the record type, or both
     * @returns {array|object} complete array or separated into groups of linked rty IDs
     */
    getLinkedRecordTypes_cache: function(rty_ID, need_separate, direction = 'to'){

        let combined = {};
        if(direction != 'from'){
            combined['linkedto'] = [];
            combined['relatedto'] = [];
        }
        if(direction != 'to'){
            combined['linkedfrom'] = [];
            combined['relatedfrom'] = [];
        }

        let rectypes = [];

        let links = $Db.rst_links();

        if(direction != 'from'){ // get 'to' record types

            if(Object.keys(links.direct).length > 0 && links.direct[rty_ID]){
                combined['linkedto'] = links.direct[rty_ID].all;
                rectypes.push(...links.direct[rty_ID].all);
            }
            if(Object.keys(links.rel_direct).length > 0 && links.rel_direct[rty_ID]){
                combined['relatedto'] = links.rel_direct[rty_ID].all;
                rectypes.push(...links.rel_direct[rty_ID].all);
            }
        }
        if(direction != 'to'){ // get 'from' record types

            if(Object.keys(links.reverse).length > 0 && links.reverse[rty_ID]){
                combined['linkedfrom'] = links.reverse[rty_ID].all;
                rectypes.push(...links.reverse[rty_ID].all);
            }
            if(Object.keys(links.rel_reverse).length > 0 && links.rel_reverse[rty_ID]){
                combined['relatedfrom'] = links.rel_reverse[rty_ID].all;
                rectypes.push(...links.rel_reverse[rty_ID].all);
            }
        }

        rectypes = [...new Set(rectypes)]; // remove dups from array version

        return need_separate ? combined : rectypes;
    },

    /**
     * @function hasFields
     * returns true if rectype has a field in its structure
     * fieldtype - base field type
     * @param {number} rty_ID 
     * @param {string} fieldtype 
     * @param {Object} db_structure //not used here, is it usefull to keep it here? 
     * @returns {boolean} is_exist
     */
    hasFields: function( rty_ID, fieldtype, db_structure ){
        
        let is_exist = false;
        
        $Db.rst(rty_ID).each(function(dty_ID, record){
            if($Db.dty(dty_ID,'dty_Type')==fieldtype){
                is_exist = true;
                return false;
            }
        });
        
        return is_exist;
    },

    //--------------------------------------------------------------------------
    
    /*
    shortcuts for working wit db definitions
    
    $Db = window.hWin.HEURIST4.dbs
    
    rty,dty,rst,rtg,dtg,trm,swf = dbdef(entityName,....)  access HEntityMgr.entity_data[entityName]
    
    set(entityName, id, field, newvalue)    
        id - localcode or concept code. For rst this are 2 params rtyID, dtyID
        field - field name. If empty returns entire record
        newvalue - assign value of field
    
    */
    
    /**
     * @function rtg
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @param {string} newValue 
     * @returns {null|Object} 
     * returns Object if newValue is "undefined"
     * returns null if newValue is assigned to field 
     */
    rtg: function(rec_ID, fieldName, newValue){
        return $Db.getset('defRecTypeGroups', rec_ID, fieldName, newValue);        
    },

    /**
     * @function dtg
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @param {string} newValue 
     * @returns {null|Object} 
     * returns Object if newValue is "undefined"
     * returns null if newValue is assigned to field  
     */
    dtg: function(rec_ID, fieldName, newValue){
        return $Db.getset('defDetailTypeGroups', rec_ID, fieldName, newValue);        
    },

    /**
     * @funcion vcg
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @param {string} newValue 
     * @returns {null|Object} 
     * returns Object if newValue is "undefined"
     * returns null if newValue is assigned to field 
     */
    vcg: function(rec_ID, fieldName, newValue){
        return $Db.getset('defVocabularyGroups', rec_ID, fieldName, newValue);        
    },
    
    /**
     * @function rty
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @param {string} newValue 
     * @returns {null|Object} 
     * returns Object if newValue is "undefined"
     * returns null if newValue is assigned to field 
     */
    rty: function(rec_ID, fieldName, newValue){
        return $Db.getset('defRecTypes', rec_ID, fieldName, newValue);        
    },

    /**
     * 
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @param {string} newValue 
     * @returns {null|Object} 
     * returns Object if newValue is "undefined"
     * returns null if newValue is assigned to field 
     */
    dty: function(rec_ID, fieldName, newValue){
        return $Db.getset('defDetailTypes', rec_ID, fieldName, newValue);        
    },

    /**
     * @function trm
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @param {string} newValue 
     * @returns {null|Object} 
     * returns Object if newValue is "undefined"
     * returns null if newValue is assigned to field
     */
    trm: function(rec_ID, fieldName, newValue){
        return $Db.getset('defTerms', rec_ID, fieldName, newValue);        
    },

    /**
     * @function swf
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @param {string} newValue 
     * @returns {null|Object} 
     * returns Object if newValue is "undefined"
     * returns null if newValue is assigned to field
     */
    swf: function(rec_ID, fieldName, newValue){
        return $Db.getset('sysWorkflowRules', rec_ID, fieldName, newValue);        
    },
    
    
    /**
     * @function rst_idx2
     * get structures for all record types
     * @returns recordset index
     */
    rst_idx2: function(){
        return window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index');
    },
    
    
    /**
     * @function rst_links
     * 
     * BASED on rectype structure
     *
     *  Returns
     * direct:   rty_ID:[{all:[],dty_ID:[rty_ID,rty_ID,....],  }]
     *  reverse:
     *  parents:  {child_rty_ID:[parents rtyIDs,...],....}
     * rel_direct:
     * rel_reverse:
     *
     * forbidden fields are ignored
     * @returns {{direct: Object, reverse: Object, parents: Object, rel_direct: Object, rel_reverse: Object } Object}
     */
    rst_links: function(){

        let rst_reverse_parent = {};  //linked FROM rectypes as a child (list of parent rectypes)
        let rst_reverse = {};    //linked FROM rectypes
        let rst_direct = {};     //linked TO rectypes

        let rst_rel_reverse = {};    //linked FROM rectypes
        let rst_rel_direct = {};     //linked TO rectypes

      
        let is_parent = false;
        let all_structs = $Db.rst_idx2();
        for (let rty_ID in all_structs){
            let recset = all_structs[rty_ID];
            recset.each2(function(dty_ID, record){

                //links
                let dty_Type = $Db.dty(dty_ID, 'dty_Type');
                if((dty_Type=='resource' || dty_Type=='relmarker') 
                    && record['rst_RequirementType']!='forbidden')
                {
                    is_parent = false;
                    
                    let ptr = $Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs');
                    if(ptr) ptr = ptr.split(',');
                    if(ptr && ptr.length>0){
                        
                            let direct;
                            let reverse;
                    
                            if(dty_Type=='resource'){
                                //LINK
                                is_parent = (record['rst_CreateChildIfRecPtr']==1);
                                
                                direct = rst_direct;
                                reverse = rst_reverse;
                            }else{
                                //RELATION
                                direct = rst_rel_direct;
                                reverse = rst_rel_reverse;
                            }      
                            
                            
                            if(!direct[rty_ID]) direct[rty_ID] = {all:[]};  
                            direct[rty_ID][dty_ID] = ptr;

                            for(let i=0; i<ptr.length; i++){
                                
                                let target_rty = ptr[i];
                                
                                //all rectypes that is referenced FROM rty_ID
                                if(direct[rty_ID].all.indexOf(target_rty)<0){
                                    direct[rty_ID].all.push(target_rty);   
                                }    
                                
                                // reverse links
                                if(!reverse[target_rty]) reverse[target_rty] = {all:[]};  

                                //all rectypes that refer TO rty_ID
                                if(reverse[target_rty].all.indexOf(rty_ID)<0){
                                    reverse[target_rty].all.push(rty_ID);        
                                }    
                                if(is_parent){
                                    if(!rst_reverse_parent[target_rty]) rst_reverse_parent[target_rty] = [];
                                    if(rst_reverse_parent[target_rty].indexOf(rty_ID)<0){
                                            rst_reverse_parent[target_rty].push(rty_ID);
                                    }
                                }
                                
                                if(!reverse[target_rty][dty_ID]) reverse[target_rty][dty_ID] = [];
                                reverse[target_rty][dty_ID].push(rty_ID)

                            }//for constraints
                    }
                }                

            });
        }
        
        return {
            parents: rst_reverse_parent,
            reverse: rst_reverse,
            direct: rst_direct,

            rel_reverse: rst_rel_reverse,
            rel_direct: rst_rel_direct
        };
        
    },


    /**
     * @function rst_links_base
     * returns links by basefield - disregard usage of field
     * @returns {Array} links
     */
    rst_links_base: function(){
        
        let links = {};
        
        $Db.dty().each2(function(dty_ID, record){
            
                let dty_Type = record['dty_Type'];
                if(dty_Type=='resource' || dty_Type=='relmarker') 
                {
                    let ptr = record['dty_PtrTargetRectypeIDs'];
                    if(ptr) ptr = ptr.split(',');
                    if(ptr && ptr.length>0){
                        for(let i=0; i<ptr.length; i++){
                            if(!links[ptr[i]]) links[ptr[i]] = [];
                            
                            links[ptr[i]].push(dty_ID);       
                        }
                    }
                }
        });

        return links;
    },    
    

    /**
     * @function rst_usage
     * returns usage (list of rty_ID) for given field
     * @param {number} dty_ID 
     * @returns {Array} usage
     */
    rst_usage: function(dty_ID){
       
        let usage = [];
        let all_structs = $Db.rst_idx2();
        for (let rty_ID in all_structs){
            if(all_structs[rty_ID].getById(dty_ID)){
                usage.push(rty_ID);
            }
        }
        return usage;
    },
    

    /**
     * @function: rst
     * @param {number} rec_ID record ID
     * @param {number} dty_ID 
     * @param {string} fieldName Field name
     * @param {string} newValue 
     * @returns {null|Object}
     */
    rst: function(rec_ID, dty_ID, fieldName, newValue){
        
            //direct access (without check and reload)
            let rectype_structure = window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index');
            
            if(rectype_structure && rectype_structure[rec_ID]){
                if(dty_ID>0){
                    return $Db.getset(rectype_structure[rec_ID], dty_ID, fieldName, newValue);                
                }else{
                    return rectype_structure[rec_ID];            
                }
            }
        return null
        
    },
    
    /**
     * @function getset 
     * @param {string} entityName 
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @param {string} newValue 
     * @returns {null|}
     */
    getset: function(entityName, rec_ID, fieldName, newValue){
        if(typeof newValue == 'undefined'){
            return $Db.get(entityName, rec_ID, fieldName);        
        }else{
            $Db.set(entityName, rec_ID, fieldName, newValue);        
            return null;
        }
    },
    
     
    /**
     * @function get
     * returns
     * recordset if Rec_ID is not defined
     * record - as object if fieldname not defined
     * @param {string} entityName 
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @returns {Object}
     */
    get: function (entityName, rec_ID, fieldName){
        //it is assumed that db definitions ara always exists on client side
        let recset =  window.hWin.HEURIST4.util.isRecordSet(entityName)?entityName
                        :window.hWin.HAPI4.EntityMgr.getEntityData(entityName); 
        
        if(recset && rec_ID>0){
            
            if(fieldName){
                return recset.fld(rec_ID, fieldName);
            }else{
                return recset.getRecord(rec_ID); //returns JSON {fieldname:value,....}
            }
            
        }else{
            return recset;
        }
        
    },

    

    /**
     * @function set
     * assign value of field OR entire record
     * @param {string|Array} entityName 
     * @param {number} rec_ID 
     * @param {string} fieldName 
     * @param {string} newValue 
     * 
     */
    set: function (entityName, rec_ID, fieldName, newValue){

        if(rec_ID>0){
        
            let recset =  window.hWin.HEURIST4.util.isRecordSet(entityName)
                            ?entityName
                            :window.hWin.HAPI4.EntityMgr.getEntityData(entityName); 
            
            if(fieldName){
                recset.setFldById(rec_ID, fieldName, newValue);
            }else{
                recset.addRecord(rec_ID, newValue);
            }
            
        }
    },
    
/*    
    //
    //
    //
    rst_set: function(rty_ID, dty_ID, fieldName, value){
        
        var dfname = $Db.rst_to_dtyField( fieldName );
        
        if(dfname){
            $Db.dty( dty_ID, dfname, value );
        }else{
        
            var recset = window.hWin.HAPI4.EntityMgr.getEntityData('defRecStructure');
            var details = window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index'); 
            if(details[rty_ID]){
                var rst_ID = details[rty_ID][dty_ID];    
                if(rst_ID>0){
                    recset.setFldById(rst_ID, fieldName, newValue);
                }else{
                    //add new basefield
                    rst_ID = recset.addRecord3({fieldName: newValue});
                    details[rty_ID][dty_ID] = rst_ID;
                }
            }
        }
    },
    //  
    // special behavior for defRecStructure
    // it returns value for given field or entire recstrucure field
    //    
    rst_idx: function(rty_ID, dty_ID, fieldName){
        
        var recset = window.hWin.HAPI4.EntityMgr.getEntityData('defRecStructure'); 
        
        if(rty_ID>0){
            
            //rty_ID:{dty_ID:rstID, ..... }
            var details = window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index');
            
            if(!details || !details[rty_ID]){
                return null;
            }else if(dty_ID>0){
                var rst_ID = details[rty_ID][dty_ID];
                
                if(!(rst_ID>0)){
                    return null;
                }else if(fieldName){
                    
                    //for backward capability
                    var dfname = $Db.rst_to_dtyField( fieldName );
                    if(dfname){
                        return $Db.dty(dty_ID, dfname);
                    }else{
                        return recset.fld(rst_ID, fieldName);        
                    }
                    
                }else{
                    return recset.getRecord(rst_ID); //json for paticular detail
                }
            }else{
                return details[rty_ID]; //array of dty_ID:rst_ID
            }
            
        }else{
            return recset;
        }
        //create group
        
        //return $Db.getset('defRecStructure', rec_ID, fieldName, newValue);        
    },
*/    

    /**
     * @function getLocalID
     * find by concept code in local definitions
     * @param {string} entity 
     * entities - prefix for rectypes, detailtypes, terms - rty, dty, trm
     * @param {string} concept_code 
     * @returns {number} - findID 
     * return local id or zero if not found
     */
    getLocalID: function(entity, concept_code){

        let findID = 0;
        let codes = null;
        
        if(typeof concept_code == 'string' && concept_code.indexOf('-')>0)
        {
            codes = concept_code.split('-');
            if(codes.length==2 && 
                (parseInt(codes[0])==0 || codes[0]==window.hWin.HAPI4.sysinfo['db_registeredid']) )
            {
                findID = codes[1];
            }
        }else if(parseInt(concept_code)>0){
            findID = concept_code;    
        }
        
        if(findID>0 && $Db[entity](findID)){
            return findID; 
        }
        
        if(codes && codes.length==2){
        
            let f_dbid = entity+'_OriginatingDBID';
            let f_id = entity+'_IDInOriginatingDB';
            
            let recset = $Db[entity]();
            recset.each2( function(id, record){
                if(record[f_dbid]==codes[0] && record[f_id]==codes[1]){
                    findID = id;
                    return false;
                }
            });
            
        }
        
        return findID;
    },
    
    //
    // get concept code by local id
    //
    getConceptID: function(entity, local_id, is_ui){
        
        let rec = $Db[entity](local_id);
        if(rec!=null){
            let dbid = rec[entity+'_OriginatingDBID'];
            let id = rec[entity+'_IDInOriginatingDB'];
            if(parseInt(dbid)>0 && parseInt(id)>0){
                return dbid+'-'+id;
            }else if( window.hWin.HAPI4.sysinfo['db_registeredid']>0 ){
                return window.hWin.HAPI4.sysinfo['db_registeredid']+'-'+local_id;
            }else{
                if(is_ui===true){
                  return '<span '
                    +'title="Concept IDs are attributed when a database is registered with the '
                    +'Heurist Reference Index using Design > Setup > Register. In the meantime only local codes are defined.">'
                    +'0000-'+local_id+'</span>';
                }else{
                    return '0000-'+local_id;    
                }
                
                
            }
        }else{
            return '';
        }
    
    },

    //
    //  Returns term ID in vocabulary by code
    //
    getTermByCode: function(vocab_id, code){

        let _terms = $Db.trm_TreeData(vocab_id, 'set');
        
        for(let i=0; i<_terms.length; i++){
            if($Db.trm(_terms[i],'trm_Code')==code){
                return _terms[i];
            }
        }
        return null;
    },

    //
    //  Returns term ID in vocabulary by label
    //
    getTermByLabel: function(vocab_id, label){

        let _terms = $Db.trm_TreeData(vocab_id, 'set');
        
        label = label.toLowerCase();
        
        for(let i=0; i<_terms.length; i++){
            if($Db.trm(_terms[i],'trm_Label').toLowerCase()==label){
                return _terms[i];
            }
        }
        return null;
    },
    
    trmHasIcon: function(term_id){
        let ids = window.hWin.HAPI4.EntityMgr.getEntityData2('trm_Icons');
        return window.hWin.HEURIST4.util.isempty(ids)   //temp - remove later
            || window.hWin.HEURIST4.util.findArrayIndex(term_id, ids)>=0; //ids.indexOf(term_id)>=0;
    },

    
    //
    // returns true if term belongs to vocabulary (including by reference)
    //
    trm_InVocab: function(vocab_id, term_id){
        
        let all_terms = $Db.trm_TreeData(vocab_id, 'set');
        
        return (window.hWin.HEURIST4.util.findArrayIndex(term_id, all_terms)>=0);
    },
    
    //
    // Comparison function for terms
    // Sort by 'Order in branch' then 'Term label'
    //
    trm_SortingById: function(a, b){

        let a_name = $Db.trm(a,'trm_Label').toLocaleUpperCase();
        let b_name = $Db.trm(b,'trm_Label').toLocaleUpperCase();
        let a_order = parseInt($Db.trm(a,'trm_OrderInBranch'), 10);
        let b_order = parseInt($Db.trm(b,'trm_OrderInBranch'), 10);

        a_order = (!a_order || a_order < 1 || isNaN(a_order)) ? null : a_order;
        b_order = (!b_order || b_order < 1 || isNaN(b_order)) ? null : b_order;

        if(a_order == null && b_order == null){ // alphabetic
            return a_name.localeCompare(b_name);
        }else if(a_order == null || b_order == null){ // null is first
            return a_order == null;
        }else{ // branch order
            return (a_order - b_order);
        }
    },

    //
    // Returns hierarchy for given vocabulary as a flat array, recordset or tree data
    // (it uses trm_Links)
    // vocab_id - id or "relation"
    // mode - 0, flat - returns recordset with defined trm_Parents 
    //        1, tree - returns treedata for fancytree
    //        2, select - return array of options for selector {key: title: depth: is_vocab}
    //        3, set  - array of ids 
    //        4, labels - flat array of labels in lower case 
    //
    trm_TreeData: function(vocab_id, mode, without_refs = false, language = ''){
        
        let recset = window.hWin.HAPI4.EntityMgr.getEntityData('defTerms');
        //parent:[children]
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let trm_ids = [];
        let res = {};
        let translated_labels = null;
        
        if(window.hWin.HEURIST4.util.isNumber(mode)){
            if(mode==1) mode='tree'
            else if(mode==2) mode='select'
            else if(mode==3) mode='set'
            else if(mode==4) mode='labels'
            else mode='flat';
        }
        
        function __addChilds(recID, lvl_parents, include_vocab){
        
            let label = $Db.trm_getLabel(recID, language);

            let node = {title: label, key: recID};
            
            if(include_vocab && lvl_parents==0){
                node.is_vocab = true;
                trm_ids.push({title: label, 
                                is_vocab: true,
                                key: recID, depth:lvl_parents});
            }

            let children = t_idx[recID]; //array of children ids trm_Links (including references)
            
            if(children && children.length>0){
                
                if(without_refs===true){
                    //remove terms by reference
                    let real_children = [];
                    $.each(children, function(i,id){
                        if(recset.fld(id,'trm_ParentTermID')==recID) real_children.push(id);
                    });
                    children = real_children;
                }

                //sort children by name
               
                children.sort($Db.trm_SortingById);
                
                if(mode=='tree'){

                    let child_nodes = [];  
                    for(let i=0; i<children.length;i++){  
                        child_nodes.push( __addChilds(children[i]) );          
                    }
                    node['children'] = child_nodes;
                    node['folder'] = true;

                }else if(mode=='select'){

                    for(let i=0; i<children.length;i++){ 
                        recID = children[i];
                        label = translated_labels ? translated_labels[recID] : recset.fld(recID, 'trm_Label');

                        trm_ids.push({title: label, 
                                      code: recset.fld(recID, 'trm_Code'),
                                      key: recID, 
                                      depth: lvl_parents});
                        __addChilds(recID, lvl_parents+1);
                    }

                }else if(mode=='set' || mode=='labels'){
                    
                    for(let i=0; i<children.length;i++){  
                        recID = children[i];
                        label = translated_labels ? translated_labels[recID] : recset.fld(recID, 'trm_Label');

                        trm_ids.push(mode=='labels'?label.toLowerCase() 
                                                   :recID);
                        __addChilds(recID);
                    }
                    
                }else{ //gather ids onlys - for recordset

                    lvl_parents = lvl_parents?lvl_parents.split(','):[];
                    lvl_parents.push(recID);

                    for(let i=0; i<children.length;i++){  
                        recID = children[i];
                        trm_ids.push(recID);

                        recset.setFldById(recID, 'trm_Parents', lvl_parents.join(','));
                        __addChilds(recID, lvl_parents.join(','));
                    }

                }
            }
            
            return node;
        }
        
        if(vocab_id=='relation'){
            //find all vocabulary with domain "relation"
            res = {'children':[]};
            let vocab_ids = $Db.trm_getVocabs('relation');
            for (let i=0; i<vocab_ids.length; i++){
                let trm_ID = vocab_ids[i];
                res['children'].push( __addChilds(trm_ID, 0, true) );
            }
            
        }else{
            res = __addChilds(vocab_id, 0, false);
        }
        
        if(mode=='tree'){
            return res['children'];
        }else if(mode=='select'){
            return trm_ids;
        }else if(mode=='set' || mode=='labels'){
            return trm_ids;
        }else{
            return recset.getSubSetByIds(trm_ids);
        }
        
    },
    
    //
    // check direct children only by id or label
    //
    trm_IsChild: function(parent_id, trm_id)
    {
        
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let children = t_idx[parent_id] ?t_idx[parent_id]:[];

        if(trm_id>0){
            return (window.hWin.HEURIST4.util.findArrayIndex(trm_id, children)>=0);
        }
        
        return false;
        
    },

    //
    // Check first level (direct children) only
    //
    trm_HasChildWithLabel: function(parent_id, trm_label, ignored_trm_id = null){

        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let children = t_idx[parent_id] ?t_idx[parent_id]:[];
        
        if(!window.hWin.HEURIST4.util.isempty(trm_label))
        {
           let recset = window.hWin.HAPI4.EntityMgr.getEntityData('defTerms');        
           trm_label = trm_label.toLowerCase();
           
           for(let i=0; i<children.length;i++){  
                let recID = children[i];
                let check_id = ignored_trm_id != recID;
                if(check_id && recset.fld(recID, 'trm_Label').toLowerCase()==trm_label)
                {
                   return true; 
                }
           }
        }
        
        return false;
    },
    
    //
    // Check direct children for privded trm_Code
    //
    trm_HasChildWithCode: function(parent_id, trm_code, ignored_trm_id = null){

        const t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        const children = t_idx[parent_id] ?t_idx[parent_id]:[];

        if(!window.hWin.HEURIST4.util.isempty(trm_code)){

           let recset = window.hWin.HAPI4.EntityMgr.getEntityData('defTerms');
           
           for(let i=0; i<children.length;i++){  
                let recID = children[i];
                let check_id = ignored_trm_id != recID;
                if(check_id && recset.fld(recID, 'trm_Code')==trm_code){
                   return true; 
                }
           }
        }
        
        return false;
    },
    
    //
    // is given term has children (including references)
    //
    trm_HasChildren: function(trm_id){
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let children = t_idx[trm_id];
        return (children && children.length>0);
    },

    //
    // change parent in links
    //
    trm_ChangeChildren: function(old_parent_id, new_parent_id){
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let children = t_idx[old_parent_id];
        
        if((children && children.length>0)){
            
            $.each(children,function(i,trm_id){
                 if($Db.trm(trm_id,'trm_ParentTermID')==old_parent_id){
                     $Db.trm(trm_id,'trm_ParentTermID',new_parent_id);
                 }
            });
            
            let target_children = t_idx[new_parent_id];
            if(target_children && target_children.length>0){
                t_idx[new_parent_id] = target_children.concat(children)
            }else{
                t_idx[new_parent_id] = children;
            }
           
        }
    },
    
    
    //
    // get all vocabularies OR for given domain
    //
    trm_getVocabs: function(domain){

        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let res = [];
        let parents = Object.keys(t_idx);
        for (let i=0; i<parents.length; i++){ //first level
            let trm_ID = parents[i];
            let trm_ParentTermID = $Db.trm(trm_ID, 'trm_ParentTermID');
            if(!(trm_ParentTermID>0)){
                if(!domain || $Db.trm(trm_ID, 'trm_Domain')==domain)
                    res.push(trm_ID);    
            }
        }
        
        return res;
    },
    
    //
    // get array of vocabularies by reference
    // (where the given term directly or by referecne belongs to)
    //
    trm_getAllVocabs: function(trm_id){
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        
        let res = [];
        let parents = Object.keys(t_idx);
        for (let i=0; i<parents.length; i++){
            let parent_ID = parents[i];
            let k = window.hWin.HEURIST4.util.findArrayIndex(trm_id, t_idx[parent_ID]);
            if(k>=0){
                let trm_ParentTermID = $Db.trm(parent_ID, 'trm_ParentTermID');
                if(trm_ParentTermID>0){
                    res = res.concat($Db.trm_getAllVocabs(parent_ID));
                }else{
                    //vocabulary!
                    res.push( parent_ID );     
                }
            }
        }
        return res;
    },

    /**
     * Creates array of objects where, key => term id & value => default/translated label
     * If a term label doesn't have a translation for the provided language,
     *  than the original label is used
     */
    trm_getTranslatedLabels: function(vocab_id, language){

        let term_ids = [];
        if(!Array.isArray(vocab_id)){
            term_ids = $Db.trm_TreeData(vocab_id, 'set');
            if(term_ids.length == 0){ // vocab id is term id(s)
                term_ids = [vocab_id];
            }else if(term_ids.indexOf(vocab_id) == -1){ // add vocab id, if missing
                term_ids.push(vocab_id);
            }
        }else{
            term_ids = vocab_id;
        }

        let translated_list = {};
        for(const id of term_ids){
            translated_list[id] = $Db.trm_getLabel(id, language);
        }

        return translated_list;
    },

    //
    //
    //
    trm_getLabel: function(term_id, language = null){


        if(!window.hWin.HEURIST4.util.isempty(language)){
            language = window.hWin.HAPI4.getLangCode3(language);    
            if(language!='ENG' && language!='ALL'){
                let translations = window.hWin.HAPI4.EntityMgr.getEntityData2('trm_Translation');

                if(translations){   
                    let rec = translations.getSubSetByRequest({trn_LanguageCode: language, 
                        trn_Source: 'trm_Label', 
                        trn_Code: term_id}).getFirstRecord();
                    if(rec && Object.keys(rec).length > 0){
                        return  translations.fld(rec, 'trn_Translation');
                    }
                }
            }
        }

        return $Db.trm(term_id, 'trm_Label');
    },
    
    //
    // remove any mention of term from hierarchy (trm_Links)
    //
    trm_RemoveLinks: function(trm_id){
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let parents = Object.keys(t_idx);
        let i = 0;
        while(i<parents.length){
            if(parents[i]==trm_id){
                delete parents[i];   
            }else{
                let k = window.hWin.HEURIST4.util.findArrayIndex(trm_id, t_idx[parents[i]]);
                if(k>=0){
                    t_idx[parents[i]].splice(k,1);
                }
                i = i +1;
            }
        }
    },
    
    //
    // add/remove terms reference links 
    // it calls server side and then update client side by changeParentInIndex
    //
    setTermReferences: function(term_IDs, new_vocab_id, new_parent_id, old_vocab_id, old_parent_id, callback){

        let default_palette_class = 'ui-heurist-design';
        
        if(new_vocab_id>0){
            
            if(!(new_parent_id>0)) new_parent_id = new_vocab_id;

            let trm_ids = $Db.trm_TreeData(new_vocab_id, 'set'); //all terms in target vocab

            let all_children = [];
            let is_exists = 0;
            for(let i=0; i<term_IDs.length; i++){
                if(window.hWin.HEURIST4.util.findArrayIndex(term_IDs[i], trm_ids)>=0){
                    is_exists = term_IDs[i];
                    break;
                }
                let children = $Db.trm_TreeData(term_IDs[i], 'set');
                for(let j=0; j<children.length; j++){
                    if(window.hWin.HEURIST4.util.findArrayIndex(children[j], trm_ids)>=0){
                        is_exists = children[j];
                        break;
                    }
                    if(all_children.indexOf(children[j])<0) all_children.push(children[j]);
                }
            }
            
            //some of selected terms are already in this vocabulary
            if(is_exists>0){
                window.hWin.HEURIST4.msg.showMsgDlg('Term <b>'+$Db.trm(is_exists,'trm_Label')
                    +'</b> is already in vocabulary <b>'+$Db.trm(new_vocab_id,'trm_Label')+'</b>', 
                    null, {title:'Terms'},
                    {default_palette_class:default_palette_class});                        
                return;
            }

            //exclude all child terms - they will be added via their parent
            let i=0;
            while(i<term_IDs.length){
                if(all_children.indexOf(term_IDs[i])<0){
                    i++;
                }else{
                    term_IDs.splice(i,1);
                } 
            }
        }
        if(old_vocab_id>0){
            if(!(old_parent_id>0)) old_parent_id = old_vocab_id;
        }

        let request = {
            'a'          : 'action',
            'reference'  : 1,
            'entity'     : 'defTerms',
            'request_id' : window.hWin.HEURIST4.util.random(),
            'old_VocabID': old_vocab_id,  
            'old_ParentTermID': old_parent_id,  
            'new_VocabID': new_vocab_id,  
            'new_ParentTermID': new_parent_id,  
            'trm_ID': term_IDs                   
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront();                                             

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status == window.hWin.ResponseStatus.OK){

                    $Db.changeParentInIndex(new_parent_id, term_IDs, old_parent_id);

                    if(window.hWin.HEURIST4.util.isFunction(callback)){
                            callback.call();
                    }

                }else{
                    if(response.status == window.hWin.ResponseStatus.ACTION_BLOCKED){
                        
                        let sMsg;
                        if(response.sysmsg && response.sysmsg.reccount){
                            
                            let s = '';
                            $.each(response.sysmsg['fields'],function(i,dty_ID){
                               s = s + $Db.dty(dty_ID,'dty_Name'); 
                            });
                              
                            sMsg = '<p>Sorry, we cannot '+(new_parent_id>0?'move':'delete')
                            + ' this term because it (or its children) is already in use in fields '
                            + ' ( '+s+' ) which reference this vocabulary</p> '
                            + ' <p><a href="'+window.hWin.HAPI4.baseURL+'?db='
                            + window.hWin.HAPI4.database+'&q=ids:' + response.sysmsg['records'].join(',') + '&nometadatadisplay=true'
                            + '" target="_blank">Show '+response.sysmsg['reccount']+' records</a> which use this term (or its descendants).</p>';
                        }else{
                            sMsg = response.message;
                        }
                        
                        window.hWin.HEURIST4.msg.showMsgDlg(sMsg, 
                            null, {title:'Term by Reference'},
                            {default_palette_class:default_palette_class});                        
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);                            
                    }
                }
        });   

    },

    //
    // change links in trm_Links (after server action)
    //
    changeParentInIndex: function(new_parent_id, term_ID, old_parent_id){

        if(new_parent_id==old_parent_id) return;

        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        if(new_parent_id>0){
            if(!t_idx[new_parent_id]) t_idx[new_parent_id] = []; 
            if(Array.isArray(term_ID)){
               

                for(let i=0; i<term_ID.length; i++)
                    if(window.hWin.HEURIST4.util.findArrayIndex(term_ID[i], t_idx[new_parent_id])<0){
                        t_idx[new_parent_id].push( term_ID[i] );    
                }

            }else if(window.hWin.HEURIST4.util.findArrayIndex(term_ID, t_idx[new_parent_id])<0)
            {
                t_idx[new_parent_id].push(term_ID);
            }

        }
        if(old_parent_id>0){
            let k = window.hWin.HEURIST4.util.findArrayIndex(term_ID, t_idx[old_parent_id]);    
            if(k>=0){
                t_idx[old_parent_id].splice(k,1);
            }
        }

    },    
    
    
        
    //--------------------------------------------------------------------------
    //
    //
    //
    applyOrder: function(recordset, prefix, callback){

        let entityName = recordset.entityName;
        let fieldId    = prefix+'_ID'; 
        let fieldOrder = prefix+'_Order';
        
        //assign new value for vcg_Order and save on server side
        let rec_order = recordset.getOrder();
        let idx = 0, len = rec_order.length;
        let fields = [];
        for(; (idx<len); idx++) {
            let record = recordset.getById(rec_order[idx]);
            let oldval = recordset.fld(record, fieldOrder);
            let newval = String(idx+1).lpad(0,3);
            if(oldval!=newval){
                recordset.setFld(record, fieldOrder, newval);        
                let fld = {};
                fld[fieldId] = rec_order[idx];
                fld[fieldOrder] = newval;
                fields.push(fld);
            }
        }
        if(fields.length>0){

            let request = {
                'a'          : 'save',
                'entity'     : entityName,
                'request_id' : window.hWin.HEURIST4.util.random(),
                'fields'     : fields                     
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
            });

        }else{
            if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
        }
    },
    
    //
    // returns record count by types
    //
    get_record_counts: function( callback )
    {
    
        $Db.needUpdateRtyCount = 0; 
        
        let request = {
                'a'       : 'counts',
                'entity'  : 'defRecTypes',
                'mode'    : 'record_count',
                //'rty_ID'  :
                'ugr_ID'  : window.hWin.HAPI4.user_id()
                };
                             
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    $Db.rty().each(function(rty_ID,rec){
                        let cnt = response.data[rty_ID]
                        if(!(cnt>0)) cnt = 0;
                        $Db.rty(rty_ID, 'rty_RecCount', cnt);
                    });
                    
                    if(window.hWin.HEURIST4.util.isFunction(callback)){
                        callback.call();
                    }
        
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });
        
    },
    
    //
    //
    //
    getTrashGroupId: function(entity){
        
        if(!(this[entity+'_trash_id']>0)){
            let name = entity+'_Name';
            let that = this;
            $Db[entity]().each2(function(id, record){
                if(record[name]=='Trash'){
                    that[entity+'_trash_id'] = id;
                    return false;
                }
            });
        }
        return this[entity+'_trash_id'];
    },
    
    //
    // prase rt:dt:rt:dt....  hierarchy - returns composed label and fields
    // used in facet and query builders
    //
    parseHierarchyCode: function(codes, top_rty_ID){

        codes = codes.split(':');

        let removeFacet = false;
        let harchy = [];
        let harchy_fields = []; //for facet.title - only field names (w/o rectype)
        let j = 0;
        while(j<codes.length){
            let rtid = codes[j];
            let dtid = codes[j+1];

            if(rtid.indexOf(',')>0){ //take first from list of rty_IDs
                rtid = rtid.split(',')[0];
            }
            
            if(rtid!=''){
                if(rtid=='any'){
                    harchy.push('');    
                    if(top_rty_ID>0) rtid = top_rty_ID;
                    
                }else if($Db.rty(rtid)==null){
                    //record type was removed - remove facet
                    removeFacet = true;
                    break;
                }else{
                    harchy.push('<b>'+$Db.rty(rtid,'rty_Name')+'</b>');    
                }
            }

            let rec_header = null;
            
            if(dtid=='title'){
                rec_header = 'Constructed record title';
            }else if(dtid=='ids'){
                rec_header = "IDs"; 
            }else if(dtid=='typeid'){
                rec_header = "type ID"; 
            }else if(dtid=='typename'){
                rec_header = "type name"; 
            }else if(dtid=='added'){
                rec_header = "Added"; 
            }else if(dtid=='modified'){
                rec_header = "Modified"; 
            }else if(dtid=='addedby'){
                rec_header = "Record author"; 
            }else if(dtid=='url'){
                rec_header = "URL"; 
            }else if(dtid=='notes'){
                rec_header = "Notes"; 
            }else if(dtid=='owner'){
                rec_header = "Owner"; 
            }else if(dtid=='access'){
                rec_header = "Visibility"; 
            }else if(dtid=='tag'){
                rec_header = "Tags"; 
            }else if(dtid=='anyfield'){
                rec_header = "Any field"; 
            }else if(dtid=='exists'){
                rec_header = `${$Db.rty(rtid, 'rty_Name')} records`;
            }
            
            if( rec_header ){
            
                    harchy.push(' . '+rec_header);
                    harchy_fields.push(rec_header);
                
            }else
            if(dtid){
                
                if(dtid.indexOf('r.')==0){
                    dtid = dtid.substr(2);
                }

                let linktype = dtid.substr(0,2);                                
                if(isNaN(Number(linktype))){
                    dtid = dtid.substr(2);

                    if(dtid>0){


                        if(linktype=='lt' || linktype=='rt'){

                            const sFieldName = (rtid=='any')
                                            ?$Db.dty(dtid, 'dty_Name')
                                            :$Db.rst(rtid, dtid, 'rst_DisplayName');

                            if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                                //field was removed - remove facet
                                removeFacet = true;
                                break;
                            }

                            harchy.push(' . '+sFieldName+' > ');
                            harchy_fields.push(sFieldName);
                        }else{
                            let from_rtid = codes[j+2];

                            const sFieldName = $Db.rst(from_rtid, dtid, 'rst_DisplayName');

                            if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                                //field was removed - remove facet
                                removeFacet = true;
                                break;
                            }

                            harchy.push(' &lt '+sFieldName+' . ');
                        }

                    }//dtid>0

                }else{

                    const sFieldName = (rtid=='any')
                                ?$Db.dty(dtid, 'dty_Name')
                                :$Db.rst(rtid, dtid, 'rst_DisplayName');

                    if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                        //field was removed - remove facet
                        removeFacet = true;
                        break;
                    }

                    harchy.push(' . '+sFieldName);
                    harchy_fields.push(sFieldName);
                }
            }
            j = j+2;
        }//while codes        



        return removeFacet? false :{harchy:harchy, harchy_fields:harchy_fields};
    },
    
    //
    // returns rules for recordtype and user
    //
    getSwfByRectype: function(_rty_ID, _usr_ID){
        
        let res = [];
        
        $Db.swf().each2(function(id, record){
            
            let rty_ID = record['swf_RecTypeID'];
            if(rty_ID == _rty_ID) 
            {
                let is_allowed = true;
                if(_usr_ID>0 && record['swf_StageRestrictedTo']){
                    //check restriction
                    let grps = record['swf_StageRestrictedTo'].split(',');
                    if(grps.indexOf(''+_usr_ID)<0){
                        is_allowed = false;
                    }
                }
                if(is_allowed){
                    res.push(record);    
                }
            }
        });
        
        return res;
    },
    
    
    //
    // Direct edit of calculated field formula
    //
    editCalculatedField: function(cfn_ID, main_callback){

        if(!(cfn_ID>0)) return;

        let request = {};
        request['cfn_ID']  = cfn_ID;
        request['a']          = 'search'; //action
        request['entity']     = 'defCalcFunctions';
        request['details']    = 'full';
        request['request_id'] = window.hWin.HEURIST4.util.random();

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    let recset = new HRecordSet(response.data);
                    if(recset.length()>0){

                        let cfn_record = recset.getFirstRecord();
                        let cfn_Content = recset.fld(cfn_record, 'cfn_FunctionSpecification');

                        //find affected record types
                        //finds all fields with rst_CalcFunctionID = cfn_ID
                        let request = {};
                        request['rst_CalcFunctionID']  = cfn_ID;
                        request['a']          = 'search'; //action
                        request['entity']     = 'defRecStructure';
                        request['details']    = 'rectype';
                        request['request_id'] = window.hWin.HEURIST4.util.random();
                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){

                                    let rectypes = null;
                                    let recset = new HRecordSet(response.data);
                                    if(recset.length()>0){
                                        rectypes = [];
                                        recset.each2(function(id, rec){
                                            rectypes.push(rec['rst_RecTypeID']);
                                        });
                                    }
                                    
                                    let popup_dialog_options = {path: 'widgets/report/', 
                                                default_palette_class: 'ui-heurist-design',
                                                title: 'Edit calculation field',
                                                keep_instance:false, 
                                                
                                                is_snippet_editor: true, 
                                                rty_ID:rectypes, 
                                                rec_ID:0,
                                                template_body:cfn_Content,
                                                
                                                onClose: function(context){
                                                    if(!context) return;

                                                    //save new formula
                                                    let request = {
                                                        'a'          : 'save',
                                                        'entity'     : 'defCalcFunctions',
                                                        'request_id' : window.hWin.HEURIST4.util.random(),
                                                        'fields'     : {cfn_ID:cfn_ID, cfn_FunctionSpecification:context}
                                                    };
                                                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                                        function(response){
                                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                                //update caclulated fields
                                                                if(rectypes && rectypes.length>0){

                                                                    let sURL = window.hWin.HAPI4.baseURL + 'admin/verification/longOperationInit.php?type=calcfields&db='
                                                                    +window.hWin.HAPI4.database+"&recTypeIDs="+rectypes.join(',');

                                                                    window.hWin.HEURIST4.msg.showDialog(sURL, {

                                                                        "close-on-blur": false,
                                                                        "no-resize": true,
                                                                        height: 400,
                                                                        width: 550,
                                                                        afterclose: main_callback
                                                                    });                                                            

                                                                }
                                                            }else{
                                                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                                            }
                                                    });
                                                }
                                    };
                                    window.hWin.HEURIST4.ui.showRecordActionDialog('reportEditor', popup_dialog_options);

                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                            }
                        );

                    }                            
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });           
    },
    
    //
    // returns list of rt and dt titles for linked hierachy rt:dt:rt:dt
    //                (in faceted search and linked geo places)
    //
    getHierarchyTitles: function( codes ){
      
        let removeFacet = false;
        let harchy = [];
        let harchy_fields = []; //for facet.title
        codes = codes.split(':');
        let j = 0;
        while(j<codes.length){
            let rtid = codes[j];
            let dtid = codes[j+1];
            
            if(rtid.indexOf(',')>0){
                rtid = rtid.split(',')[0];
            }
            
            if($Db.rty(rtid)==null){
                //record type was removed - remove facet
                removeFacet = true;
                break;
            }
            
            harchy.push('<b>'+$Db.rty(rtid,'rty_Name')+'</b>');
            
            if(j==0 && dtid=='title'){
               harchy_fields.push('Constructed record title');
            }else
            if(dtid=='modified'){
               harchy_fields.push("Modified"); 
            }else if(dtid=='added'){
               harchy_fields.push("Added"); 
            }else if(dtid=='ids'){
               harchy_fields.push("Record ID"); 
            }else if(dtid=='typeid' || dtid=='t'){
               harchy_fields.push("Type ID"); 
            }else if(dtid=='typename'){ //record type name rty_Name
               harchy_fields.push("Type Name"); 
            }else if(dtid=='addedby'){
               harchy_fields.push("Creator"); 
            }else if(dtid=='owner'){
               harchy_fields.push("Record Owner"); 
            }else if(dtid=='access'){
               harchy_fields.push("Record Visibility"); 
            }else if(dtid=='notes'){
               harchy_fields.push("Notes"); 
            }else if(dtid=='url'){
               harchy_fields.push("URL"); 
            }else if(dtid=='tag'){
               harchy_fields.push("Tags"); 
            }
            
            if(dtid.indexOf('r.')==0){
                dtid = dtid.substr(2);
            }
            
            let linktype = dtid.substr(0,2);                                
            if(isNaN(Number(linktype))){
                dtid = dtid.substr(2);
                
                if(dtid>0){
                
                    
                if(linktype=='lt' || linktype=='rt'){
                    
                    const sFieldName = $Db.rst(rtid, dtid, 'rst_DisplayName');
                    
                    if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                        //field was removed - remove facet
                        removeFacet = true;
                        break;
                    }
                    
                    harchy.push(' . '+sFieldName+' &gt ');
                    harchy_fields.push(sFieldName);
                }else{
                    //reverse link
                    const from_rtid = codes[j+2];

                    const sFieldName = $Db.rst(from_rtid, dtid, 'rst_DisplayName');
                    
                    if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                        //field was removed - remove facet
                        removeFacet = true;
                        break;
                    }
                    
                    harchy.push(' &lt '+sFieldName+' . ');
                    harchy_fields.push(sFieldName);
                }
                
                }//dtid>0
                
            }else{

                const sFieldName = $Db.rst(rtid, dtid, 'rst_DisplayName');
                
                if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                    //field was removed - remove facet
                    removeFacet = true;
                    break;
                }
                
                harchy.push(' . '+sFieldName);
                harchy_fields.push(sFieldName);
            }
            j = j+2;
        }//while codes
       
        if(removeFacet){
            return false;
        }else{
            return {harchy:harchy, harchy_fields:harchy_fields};
        }         

    },

    /**
     * Retrieve each base field and each instance of the base field
     * @param {number|Array} rty_IDs - Single (or array) of record type ids
     * @param {number} mode - 
     *        0: flat data; [ [dty id, dty label, [ rst label 1, rst label 2, ... ], show_in_lists ], ... ]
     *        1: for dropdowns [ {key: dty id, title: dty label, show_in_lists: true|false}, {key: dty id, title: rst label 1, depth: 2}, ... ]
     * @param {string|Array} allowed_types - array of allowed detail types | 'all'
     * @param {int|Array} ignored_dty_id - base fields to ignore
     * @returns {Array} field data or dropdown options or fanctree nodes
     */
    getBaseFieldInstances: function(rty_IDs, mode = 0, allowed_types = 'all', ignored_dty_id = [], list_all_fields = true){

        let fields = [];

        if(!rty_IDs || rty_IDs == 'all'){ // get all ids
            rty_IDs = $Db.rty().getIds();
        }

        if(!Array.isArray(rty_IDs) && rty_IDs > 0){
            rty_IDs = [ rty_IDs ];
        }
        if(!Array.isArray(ignored_dty_id) && ignored_dty_id > 0){
            ignored_dty_id = [ ignored_dty_id ];
        }

        if(!rty_IDs || !window.hWin.HEURIST4.util.isArrayNotEmpty(rty_IDs)){
            return [];
        }

        let last_idx = 0; // current index count
        let arr_idx = {}; // id to array idx
        for(const rty_id of rty_IDs){ // Get base fields and instances for each rectype

		
			const rty_name = $Db.rty(rty_id, 'rty_Name');

			const recset = $Db.rst(rty_id);

			if(window.hWin.HEURIST4.util.isempty(recset)) { continue; }

			recset.each2(function(dty_id, details){

                if(dty_id == ignored_dty_id || ignored_dty_id.indexOf(dty_id) >= 0){
                    return;
                }

				const dty = $Db.dty(dty_id);
                const dty_name = dty['dty_Name'];

                if(allowed_types != 'all' && allowed_types.indexOf(dty['dty_Type']) < 0){
                    return;
                }

				if(!Object.hasOwn(arr_idx, dty_id)) {
                    let list_fld = !list_all_fields && $Db.dty(dty_id, 'dty_ShowInLists') == 0;
                    arr_idx[dty_id] = last_idx;
                    last_idx ++;
					fields.push( [ dty_id, dty_name, [], list_fld ] );
				}

                const dty_idx = arr_idx[dty_id];
				const rst_name = rty_name + "." + details["rst_DisplayName"];

				fields[dty_idx][2].push(rst_name);
			});
		}

        // sort base field names
        fields.sort((arr1, arr2) => {
            let a = arr1[1].toLocaleUpperCase();
            let b = arr2[1].toLocaleUpperCase();
            return a.localeCompare(b);
        });

        let processed_fields = [];

		for(const field of fields){ // sort rst field names + additional processing for different modes

			field[2].sort((a, b) => {
                a = a.toLocaleUpperCase();
                b = b.toLocaleUpperCase();
                return a.localeCompare(b);
            });

            const dty_id = field[0];
            const dty_title = field[1];
            const rst_titles = field[2];
            const show_in_list = field[3];

            if(mode == 1){
               
                processed_fields.push({key: dty_id, title: dty_title, hidden: show_in_list});

                for(const rst_title of rst_titles){
                    processed_fields.push({key: dty_id, title: rst_title, depth: 1, hidden: show_in_list});
                }
            }
            /*  needs testing
            else if(false && mode == 2){ 

                let node = {
                    'title': dty_title,
                    'key': dty_id,
                    'code': dty_id,
                    'children': []
                };

                let sub_node = {
                    'key': dty_id,
                    'code': dty_id
                };

                for(const rst_title of rst_titles){
                    sub_node['title'] = rst_title;
                    node['children'].push(sub_node);
                }

                processed_fields.push(node);
            }
            */
		}

        if(mode == 0 || mode == 2){
            return fields;
        }else{
            return processed_fields;
        }
    },

    /**
     * Get detail types common to all provided record types
     * 
     * @param {number|Array} rty_IDs - array of record type ids to include
     * @param {number|Array} ignored_dty_id - array of detail type ids to ignore/skip
     * @returns {Array} - array of dty ids common to provided record types
     */
    getSharedFields: function(rty_IDs, ignored_dty_id = []){

        let dty_IDs = [];

        if(!rty_IDs){ // get all ids
            return dty_IDs;
        }

        if(!Array.isArray(rty_IDs) && rty_IDs > 0){
            rty_IDs = [ rty_IDs ];
        }
        if(!Array.isArray(ignored_dty_id) && ignored_dty_id > 0){
            ignored_dty_id = [ ignored_dty_id ];
        }

        if(!rty_IDs || !window.hWin.HEURIST4.util.isArrayNotEmpty(rty_IDs)){
            return dty_IDs;
        }

        for(const rty_ID of rty_IDs){

            let fields = $Db.rst(rty_ID).getIds();
            if(dty_IDs.length == 0){
                dty_IDs = fields;
                continue;
            }

            dty_IDs = dty_IDs.filter(fld_id => fields.includes(fld_id));
        }

        if(dty_IDs.length > 0 && ignored_dty_id.length > 0){
            dty_IDs = dty_IDs.filter(fld_id => !ignored_dty_id.includes(fld_id));
        }

        return dty_IDs;

    }

}//end dbs

}
//alias
window.$Db = window.hWin.HEURIST4.dbs;