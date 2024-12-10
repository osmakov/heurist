/**
* Media Viewer. It accept list of media in rec_Files or fills it with search_initial
* It may work in 3 modes
* 1) creates thumbnails and opens fancybox on click
* 2) use existing thumbnails with attributes data-id or data-recid 
* 3) opens fancybox on method show
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


$.widget( "heurist.mediaViewer", {

    // default options
    options: {
        rec_Files: null, //array of objects {rec_ID, (obfuscation_file_)id, mimeType, filename, extrernal}
        search_initial:null, //if rec_Files are not defined - use this search query to retrieve rec_Files
        
        selector: null,  //if defined it does not render thumbnails, it searches for elements that will trigger fancybox
        
        openInPopup: true, //show video in popup
        showLink: false,   // show link to open full view in new tab or download
        
        baseURL: null,  //define when mediaViewer is run outside standard Heurist environment
        database: null
    },

    // the constructor
    _create: function() {
        this.mediacontent = this.element;
        
        if(window.hWin && window.hWin.HAPI4){
            if(!this.options.baseURL){
                this.options.baseURL = window.hWin.HAPI4.baseURL;
            }
            if(!this.options.database){
                this.options.database = window.hWin.HAPI4.database;
            }
        }
        
        this._refresh();
        
    }, //end _create
    
    _setOptions: function() {
        this._superApply( arguments );
        this._refresh();
    },
    
    _refresh: function(){
        
        if(this.options.search_initial)
        {
            
            let request = {
                    q: this.options.search_initial,
                    restapi: 1,
                    zip: 1,
                    extended: 3, 
                    format:'json'};
                        
            let that = this;
                        
            window.hWin.HAPI4.RecordMgr.search_new(request, function(response){
                if(window.hWin.HEURIST4.util.isJSON(response)) {
                   that.options.rec_Files = response['records'];
                   if(that.options.rec_Files && that.options.rec_Files.length>0){
                        that._initControls();    
                   }                
                   
                }else{
                   window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
            
        }else{
            this._initControls();    
        }
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        this.mediacontent.empty();
    }

    //
    // init controls
    //    
    ,_initControls: function(){
        
        if(this.options.selector){
           //thumbnails already exist
           this._initThumbnails( this.options.selector );
        }else{
            this._renderThumbnails();
        }
        
    }
    
    , _htmlUnescape:function(text) {
        if(text){
            let e = document.createElement("textarea");
            e.innerHTML = text;
            // handle case of empty input
            return e.childNodes.length === 0 ? "" : e.childNodes[0].nodeValue;    
        }else{
            return null;
        }
    }

    //
    //
    //    
    ,_renderThumbnails: function(title){

        this.mediacontent.empty();
        
        if(Array.isArray(this.options.rec_Files))
        {

            for (let idx in this.options.rec_Files){
                if(idx>=0){ 

                    let obf_recID, mimeType, filetitle = '', filename = null, external_url= null, rec_ID=0, mode_3d_viewer = '';

                    let file = this.options.rec_Files[idx];
                    
                    if($.isPlainObject(file)){
                        rec_ID = file.rec_ID;
                        obf_recID = file.id;
                        mimeType = file.mimeType;
                        filename = file.filename; //to detect _iiif or _tiled
                        filetitle = file.title;
                        external_url = this._htmlUnescape(file.external);
                        mode_3d_viewer = file.mode_3d_viewer; //3dhop or 3d or ''
                    }else{
                        obf_recID = file[0];
                        mimeType = file[1];
                    }
                    if(!filetitle) filetitle = title;
                    if(!mimeType) mimeType = '';

                    let fileURL = this.options.baseURL+'?db=' + this.options.database //+ (needplayer?'&player=1':'')
                                 + '&file='+obf_recID;

                    let thumbURL =  this.options.baseURL+'?db=' +  this.options.database 
                                 + '&thumb='+obf_recID


                    //thumbnail preview
                    let $alink = $('<a>')
                            .attr('data-id', obf_recID)
                            .appendTo($("<div>").css({cursor:'pointer',height:'auto','display':'inline-block','data-caption':filetitle})
                            .appendTo(this.mediacontent));
                        
                    $('<img>', {src: thumbURL, title:filetitle})
                            .css({border: '2px solid #FFF', margin:'5px', 'box-shadow': '0 2px 4px #bbb', width:'200px'})
                            .appendTo($alink);
                
                if(this.options.showLink){
                    $('<br>').appendTo(this.mediacontent);
                    if(external_url || filename === '_iiif' || filename === '_remote')  //@todo check preferred source
                    {
                        if(!external_url) external_url = fileURL; 
                               
                        if(filename.indexOf('_iiif')>=0){
                            external_url =  this.options.baseURL 
                                     + 'hclient/widgets/viewers/miradorViewer.php?db='
                                     +  this.options.database
                                     + '&'+filename.substring(1)+'='+obf_recID;  //either iiif or iiif_image
                                     
                            if(rec_ID>0){
                                external_url =  external_url + '&recID='+rec_ID;    
                            }
                        }                
                       
                        $('<a href="'+external_url+'" target="_blank">'
                    +'<span class="ui-icon ui-icon-mirador" style="width:12px;height:12px;margin-left:5px;font-size:1em;display:inline-block;vertical-align: middle;'
                    +'filter: invert(35%) sepia(91%) saturate(792%) hue-rotate(174deg) brightness(96%) contrast(89%);'
                    +'"></span>&nbsp;open in Mirador</a>')
                        .appendTo(this.mediacontent);
                       
                       /* 
                       $('<a>', {href:external_url, target:'_blank'})
                                .text('OPEN IN NEW TAB')
                                .addClass('external-link')
                                .appendTo(this.mediacontent);
                       */
                    }else{
                        $('<a>', {href:(fileURL+'&download=1'), target:'_surf'}) 
                                .text('DOWNLOAD')
                                .addClass('external-link image_tool')
                                .appendTo(this.mediacontent);
                                
                        if (mode_3d_viewer!=null && mode_3d_viewer!='') { //3d or 3dhop

                            external_url =  this.options.baseURL 
                                     + 'hclient/widgets/viewers/'+mode_3d_viewer+'Viewer.php?db='
                                     +  this.options.database
                                     + '&file='+obf_recID;
                        
                        $('<a href="'+external_url+'" target="_blank">'
                    +'<span class="ui-icon ui-icon-box" style="width:12px;height:12px;margin-left:5px;font-size:1em;display:inline-block;vertical-align: middle;'
                    +'"></span>&nbsp;open in 3D viewer</a>')
                        .appendTo(this.mediacontent);
                        
                        }
                    }
                    $('<br>').appendTo(this.mediacontent);
                }//showLink

                }
            }//for

            this.mediacontent.show();
            
            this._initThumbnails('a[data-id]');
        }
    }
    
    //
    //
    //
    ,_initThumbnails: function(selector){
        
        let eles = this.mediacontent.find(selector);
        let that = this;
        
        $.each(eles, function(idx, $alink){
            
            $alink = $($alink);
            let recid = $alink.attr('data-id');

            if(recid)
            for (let idx in that.options.rec_Files){
                if(idx>=0){  //skip first
                    let file = that.options.rec_Files[idx];
                    if(recid && recid==file.rec_ID || recid==file.id){ 
                        //found
                        let rec_ID = file.rec_ID,
                        obf_recID = file.id,
                        mimeType = file.mimeType,
                        filename = file.filename, //to detect _iiif or _tiled
                        filetitle = file.title,
                        external_url = that._htmlUnescape(file.external),
                        mode_3d_viewer = file.mode_3d_viewer;
                    
                        if(!mimeType) mimeType = '';

                        let fileURL = that.options.baseURL+'?db=' + that.options.database //+ (needplayer?'&player=1':'')
                                     + '&file='+obf_recID;

                        let thumbURL =  that.options.baseURL+'?db=' +  that.options.database 
                                     + '&thumb='+obf_recID
                    
                        if(filename && filename.indexOf('_iiif') === 0){ //manifest

                            let param = 'manifest';
                            if(filename == '_iiif_image'){
                                
                                if(rec_ID>0){
                                    //param = 'q'; //it adds format=iiif in miradorViewer.php
                                   
                                    //$alink.attr('data-id', obf_recID);  
                                    param = 'q=ids:'+rec_ID;
                                }else{
                                    param = 'iiif_image='+obf_recID;
                                } 
                            }else{
                                //param = 'manifest='+obf_recID;    
                                param = 'iiif='+obf_recID;    
                            }
                            
                            
                            $alink
                                .css('cursor','pointer')
                                .attr('data-id', obf_recID)                            
                                .attr('data-iiif', param);
                            
                        
                            //for link below thumb                        
                            external_url =  that.options.baseURL 
                                     + 'hclient/widgets/viewers/miradorViewer.php?db='
                                     +  that.options.database
                                     + '&' + param;
                                     
                            if(rec_ID>0){
                                external_url =  external_url + '&recID='+rec_ID;    
                            }
                                     
                            function __openMiradorViewer(e){

                                let evt = e;

                                if(evt.already_checked!==true && window.hWin && window.hWin.HAPI4 && window.hWin.HAPI4.has_access()){
                                    window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('2-101', 2,
                                        'In order to add Annotation to image you have to import "Annotation" record type',
                                        function(){
                                            evt.already_checked = true;
                                            __openMiradorViewer(evt);
                                    });
                                    return;
                                }

                                let ele = $(e.target)
                                if(!ele.attr('data-iiif')){
                                    ele = ele.parents('[data-iiif]');
                                }
                                let param  = ele.attr('data-iiif');
                                let obf_recID = ele.attr('data-id');

                                let url =  that.options.baseURL 
                                + 'hclient/widgets/viewers/miradorViewer.php?db='
                                +  that.options.database
                                + '&' + param;

                                if(rec_ID>0){
                                    url =  url + '&recID='+rec_ID;    
                                }

                                if(window.hWin && window.hWin.HEURIST4){
                                    //borderless:true, 
                                    window.hWin.HEURIST4.msg.showDialog(url, 
                                        {dialogid:'mirador-viewer',
                                            //resizable:false, draggable: false, 
                                            //maximize:true, 
                                            default_palette_class: 'ui-heurist-explore',
                                            width:'90%',height:'95%',
                                            allowfullscreen:true,'padding-content':'0px'});   

                                    let $dlg = $(window.hWin?window.hWin.document:document).find('body #mirador-viewer');

                                    $dlg.parent().css('top','50px');
                                }else{
                                    window.open(url, '_blank');        
                                }

                            };
                            
                            //on thumbnail click
                            that._on($alink, {click:__openMiradorViewer});
                            
                            
                        }else
                        if(mode_3d_viewer!=null && mode_3d_viewer!=''){
                            
                            $alink
                                .css('cursor','pointer')
                                .attr('data-id', obf_recID);
                                
                            //on thumbnail click
                            that._on($alink, {click:function(e){
                        
                                  let ele = $(e.target)
                                  if(!ele.attr('data-id')){
                                      ele = ele.parents('[data-id]');
                                  }
                                  let obf_recID = ele.attr('data-id');
                                  
                                  let url =  that.options.baseURL 
                                     + 'hclient/widgets/viewers/'+mode_3d_viewer+'Viewer.php?db='
                                     +  that.options.database+'&file='+obf_recID;
                                    
                                  if(window.hWin && window.hWin.HEURIST4){
                                        //borderless:true, 
                                        window.hWin.HEURIST4.msg.showDialog(url, 
                                            {dialogid:'mirador-viewer',
                                             //resizable:false, draggable: false, 
                                             //maximize:true, 
                                             default_palette_class: 'ui-heurist-explore',
                                             width:'90%',height:'95%',
                                             allowfullscreen:true,'padding-content':'0px'});   
                                             
                                       
                                       
                                  }else{
                                        window.open(url, '_blank');        
                                  }
                                     
                                     
                            }});
                            
                            
                        }else
                        if(mimeType.indexOf('image')===0){
                            $alink.attr('data-href', external_url?external_url:fileURL+'&fancybox=1')
                                  .attr('data-type', 'image')
                                  .attr('data-src', external_url?external_url:fileURL+'&fancybox=1')
                                  .attr('data-myfancybox','fb-images')
                                  .css('cursor','pointer')
                                  .attr('data-thumb', thumbURL);
                            
                            if(file.caption) $alink.attr('data-caption', file.caption);
                            
                        }else
                        if(mimeType=='application/pdf' || mimeType.indexOf('audio/')===0 || mimeType.indexOf('video/')===0){

                            external_url = fileURL  + '&mode=page';
                           
                            
                            if(that.options.selector || (that.options.openInPopup && mimeType.indexOf('audio/')!==0)){
                                
                                fileURL = fileURL  + '&mode=tag&fancybox=1';
                                
                                $alink.attr('data-href','{}')
                                    .attr('data-src', fileURL)
                                    .attr('data-type', 'ajax')
                                    .attr('data-myfancybox','fb-images')
                                    .css('cursor','pointer')
                                    .attr('data-thumb', thumbURL);
                                
                                if(file.caption) $alink.attr('data-caption', file.caption);
                                
                            }else{
                                fileURL = fileURL  + '&mode=tag';
                                $alink.hide();
                                let ele = $('<div>').css({width:'90%',height:'160px'}).load( fileURL );
                                ele.insertAfter($alink);
                            }
                        }                        
                    }
                }
            }//for
            
        });
        
        
        let fancy_opts = { selectorParentEl:this.mediacontent, 
                            selector : '[data-myfancybox="fb-images"]', 
                            loop:true};
        $('body').off("click.fb-start"); //was unbind
       
        
        if(window.hWin && window.hWin.HAPI4 && window.hWin.HAPI4.fancybox){ 
                window.hWin.HAPI4.fancybox( fancy_opts );
        }else if (typeof $.fn.fancybox === 'function'){ //  window.hWin.HEURIST4.util.isFunction
                $.fn.fancybox( fancy_opts );
        }
        
    },

    //
    // opens explicitely
    //    
    show: function (){
        this.mediacontent.find(this.options.selector+':first').trigger('click');
    },
    
    //
    // removes of event handlers 
    //
    clearAll: function (){

        if(this.options.selector){
            this.mediacontent
                    .off("click.fb-start", '[data-myfancybox="fb-images"]'); //this.options.selector
        }
    }
    


});
