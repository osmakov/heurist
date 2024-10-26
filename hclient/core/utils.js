/**
*  Various utility functions
*
* @todo - split to generic utilities and UI utilities
* @todo - split utilities for hapi and load them dynamically from hapi
*
* @see editing_input.js
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

/* global ActiveXObject,Temporal,TDate */

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.util) 
{

window.hWin.HEURIST4.util = {


    isnull: function(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    },

    isempty: function(obj){
        if (window.hWin.HEURIST4.util.isnull(obj)){
            return true;
        }else if(Array.isArray(obj)){
            return obj.length<1;
        }else{
            return (obj==="") || (obj==="null");
        }

    },

    istrue: function(val, def){
        
        def = window.hWin.HEURIST4.util.isnull(def)?true:def;
        
        if(window.hWin.HEURIST4.util.isnull(val)){
            return def;
        }else if(val===true){
            return true;
        }else if(typeof obj==='string'){
            val =  val.toLowerCase();
            return val=='yes' || val=='y'  || val=='true' || val=='t' || val=='1';
        }else{
            return val==1;
        }
    },
    
    isColor: function (strColor){
        if (window.hWin.HEURIST4.util.isempty(strColor)) {
            return false
        }
        let s = new Option().style;
        s.color = strColor;
        return s.color == strColor;
    },   

    byteLength: function(str) {
      // returns the byte length of an utf8 string
      let s = str.length;
      let i=str.length-1;
      while (i>=0)
      {
        let code = str.charCodeAt(i);
        if (code > 0x7f && code <= 0x7ff) s++;
        else if (code > 0x7ff && code <= 0xffff) s+=2;
        if (code >= 0xDC00 && code <= 0xDFFF) i--; //trail surrogate
        i--;
      }
      return s;
    },    
    
    //
    //remove ian's trailing &gt; used to designate a pointer field and replace consistently with >>
    //
    trim_IanGt: function(name){
            if(name){
                name = name.trim();
                if(name.substr(name.length-1,1)=='>') name = name.substr(0,name.length-2);
                return name;
            }else{
                return '';
            }
    },
    
    isFunction: function(f){
        return typeof f === 'function';
    },
    
    isNumber: function (n) {
       
        return !isNaN(parseFloat(n)) && isFinite(n);
    },
    
    isPositiveInt: function (n) {
        if(window.hWin.HEURIST4.util.isempty(n) || !(typeof n === 'string' || typeof n === 'number')){
            return false;
        }

        if(typeof n === 'string'){
            n = parseInt(n);    
        }
        
        return !isNaN(n) && n>0;
    },

    //
    //
    //
    checkRegexp:function ( o, regexp ) {
        if ( !( regexp.test( o.val() ) ) ) {
            o.addClass( "ui-state-error" );
            return false;
        } else {
            return true;
        }
    },
    
    //
    // From jquery.validate.js (by joern), contributed by Scott Gonzalez: http://projects.scottsplayground.com/email_address_validation/
    //    
    checkEmail:function ( email_input ) {

    
//opt1 /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|.(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
//opt2 /^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i    
//opt3 /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i
        const re = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;    
    
        return window.hWin.HEURIST4.util.checkRegexp( email_input, re);
    },    

    cloneJSON:function (data){
        try{
            return JSON.parse(JSON.stringify(data));
        }catch (ex2){
            //cannot clone json array
            return [];
        }
    },

    // get current font size in em
    em: function(input) {
        let emSize = parseFloat($("body").css("font-size"));
        return (emSize * input);
    },

    em2px: function(ele) {
        
        if(!ele) {
            ele = $("body");   
        }
        //else {
           
        //}
        const fs = ele.css('font-size');
        /*
        var rele = $('<span>').html(input).css('font-size', fs).appendTo(ele);
        var res = rele.width();
        rele.remove();
        return res;
        */
        return parseFloat(fs);
        
    },
    
    // get current font size in pixels
    px: function(input, ele) {
        let emSize = window.hWin.HEURIST4.util.em2px(ele);
        return (input.length * emSize);
    },

    //
    // enable or disable element
    //
    setDisabled: function(element, mode){
        if(element){
            if(!Array.isArray(element)){
                element = [element];
            }
            $.each(element, function(idx, ele){
                
                if (!ele.jquery) {
                    ele = $(ele);    
                }
                
                if( ($.heurist.hSelect !=='undefined') && window.hWin.HEURIST4.util.isFunction($.heurist.hSelect) && ele.hSelect('instance')!=undefined){              

                    if (mode) {
                        ele.hSelect( 'disable' );
                    }else{
                        ele.hSelect( 'enable' );    
                    }

                }else{
                    if (mode) {
                        ele.prop('disabled', 'disabled');
                        ele.addClass('ui-state-disabled');
                    }else{
                        ele.removeProp('disabled');
                        ele.removeClass('ui-state-disabled ui-button-disabled');
                    }
                }
            });
        }
    },
    
    isIE: function () {
        let myNav = navigator.userAgent.toLowerCase();
        return (myNav.indexOf('msie') != -1) ? parseInt(myNav.split('msie')[1]) : false;
    },
    
    //
    //
    //
    checkProtocolSupport: function(url){
        
        if (window.hWin.HEURIST4.util.isIE()) { //This bastard always needs special treatment
        
            if (typeof (navigator.msLaunchUri) == typeof (Function)) {
                
                navigator.msLaunchUri(url,
                    function () { /* Success */ },
                    function () { /* Failure */ window.hWin.HEURIST4.msg.showMsgErr('Not supported') });
                return;
            }
                
            try {
                new ActiveXObject("Plugin.mailto");
            } catch (e) {
                //not installed
            }
        } else { //firefox,chrome,opera
           
            let mime = navigator.mimeTypes['application/x-mailto'];
            if(mime) {
                //installed
            } else {
                //not installed
                 window.hWin.HEURIST4.msg.showMsgErr('Not supported');
            }
        }      
      
        
    },

    //  1 - encode ../
    //  2 - use encodeURIComponent  
    //  3 - JSON.stringify
    //
    encodeRequest: function(request, params, need_encode){
        
        if(!(need_encode>0)){
            need_encode = window.hWin.HAPI4.sysinfo['need_encode'];
        }
        
        if(need_encode>0){
            let f_encode = null;
            
            if(need_encode==2 || need_encode==1){
                f_encode = encodeURIComponent;
               
            }else if(need_encode==3){
                f_encode = JSON.stringify;
            }
                
            if(f_encode != null){
                for(let i=0; i<params.length; i++){
                    if(request[params[i]]){
                        request[params[i]] = f_encode(request[params[i]]);
                    }
                }
                
            }
            request.details_encoded = need_encode;
        }
    },
    
    //
    // NOT USED. Replace ../  to ^^/     style= to xxx_style=
    //
    encodeSuspectedSequences: function (val) {
        
        if(typeof val !== 'string' && (Array.isArray(val) || $.isPlainObject(val))) {
            val = JSON.stringify(val);
        }
        return encodeURIComponent(val.replace(/(\.\.\/)/g, '^^/').replace(/( style=)/g,' xxx_style='));
    },
    
    //
    // returns json or false
    //
    isJSON: function(value){
        
            let res = false;
            try {
                if(typeof value === 'string'){
                    value = value.replace(/[\n\r]+/g, '');
                    value = JSON.parse(value);    
                }
                if(Array.isArray(value) || $.isPlainObject(value)){
                    res = value;
                }
            }
            catch (err) {
                res = false;
            } 
            
            return res;       
    },
    
    //
    // Extract parameter from given URL or from current window.location.search
    //
    getUrlParameter: function(name, query){

        if(!query){
            query = window.location.search;
        }

        const urlParams = new URLSearchParams(query);
                
        if(urlParams.has(name)){
            return urlParams.get(name); //already decoded decodeURIComponent
        }else{
            return null;
        }
        
        /* old way
        var regexS = "[\\?&]"+name+"=([^&#]*)";
        var regex = new RegExp( regexS );
        var results = regex.exec( query );

        if( results == null ) {
            return null;
        } else {
            try{
                return decodeURIComponent(results[1]);
            }catch (ex){
                return results[1];
            }
        }
        */
    },
    
    
    /**
     * Get the URL parameters
     * source: https://css-tricks.com/snippets/javascript/get-url-variables/
     * @param  {String} url The URL
     * @return {Object}     The URL parameters
     */
    getUrlParams: function (url) {
        
        let parser = document.createElement('a');
        parser.href = url;
        let query = parser.search.substring(1);
        
        let params = window.hWin.HEURIST4.util.getParamsFromString(query, '&', true);
        
        let vars = query.split('&');
        for (let i = 0; i < vars.length; i++) {
            let pair = vars[i].split('=');
            params[pair[0]] = decodeURIComponent(pair[1]);
        }
        return params;
    },

    getParamsFromString: function (url, sep='&', decode=true) {
        let params = {};
        let vars = url.split(sep);
        for (let i = 0; i < vars.length; i++) {
            let pair = vars[i].split('=');
            if(decode){
                params[pair[0]] = decodeURIComponent(pair[1]);    
            }else{
                params[pair[0]] = pair[1];
            }
        }
        return params;
    },
    
    
    isArrayNotEmpty: function (a){
        return (Array.isArray(a) && a.length>0);
    },

    isArray: function (a)
    {
        return Array.isArray(a);
    },
    
    isGeoJSON: function(a, allowempty){
        
        if(allowempty && Array.isArray(a) && a.length==0){
            return true;   
        }else if($.isPlainObject(a)){
            return (a['type']=='Feature' || a['type']=='FeatureCollection' || a['type']=='GeometryCollection');
        }else{
            return (window.hWin.HEURIST4.util.isArrayNotEmpty(a) && (a[0]['type']=='Feature' || a[0]['type']=='FeatureCollection'));
        }
    },

    /*
    htmlEscape: function(s) {
        return s?s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;").replace(/"/g, "&#34;"):'';
    },
    */
    
    //
    // see php htmlspecialchars
    //
    htmlEscape: function (text) {
      let map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',  //&#34
        "'": '&#039;'
      };
      
      if(window.hWin.HEURIST4.util.isempty(text)){
          return '';
      }else{
          return (''+text).replace(/[&<>"']/g, function(m) { return map[m]; })
      }
    },  
    
    //
    // whitelist e.g. "p, img"
    //
    stripTags: function(text, whitelist){
        
        if(whitelist===false){ 
            
            return window.hWin.HEURIST4.util.htmlEscape(text);             
        }else{
            
            let link = document.createElement("span");
            link.style.display = "none";
            link.innerHTML = text;
            document.body.appendChild(link);
            
            //find('*')
            let eles = $(link).find('*');
            if(!window.hWin.HEURIST4.util.isempty(whitelist)){
                eles = eles.not(whitelist);
            }
            
            eles.each(function() {
                let content = $(this).contents();
                $(this).replaceWith(content);
            });   
            
            if(window.hWin.HEURIST4.util.isempty(whitelist)){
                text =  $(link).text().trim();    
            }else{
                text =  $(link).html();    
            }
            
            document.body.removeChild(link); 
            link = null;
            
            return text;
        }
        
    },
    
    stripFirstElement: function(text){
        
            let link = document.createElement("span");
            link.style.display = "none";
            link.innerHTML = text;
            document.body.appendChild(link);
            
            let eles = $(link).find('*');
            eles.first().remove();
            text =  $(link).html();
                
            document.body.removeChild(link); 
            link = null;
            
            return text;
    },
    
    //
    //
    //
    stripScripts: function(s) {

        //jquery way        
        return $('<div>').append($.parseHTML(s)).html();
        
        //vanilla way
        /*
        var div = document.createElement('div');
        div.innerHTML = s;
        var scripts = div.getElementsByTagName('script');
        var i = scripts.length;
        while (i--) {
            scripts[i].parentNode.removeChild(scripts[i]);
        }
        return div.innerHTML;
        */
    },

    //
    //
    //
    isObject: function (a)
    {
        return Object.prototype.toString.apply(a) === '[object Object]';
    },

    stopEvent: function(e){
        if (!e) e = window.event;

        if (e) {
            e.cancelBubble = true;
            if (e.stopPropagation) e.stopPropagation();
            e.returnValue = false;
            e.preventDefault();
        }
        return e;
    },

    //
    //
    //                    
    interpretServerError: function(jqXHR, url, request_code){
    
        let err_message = '';
        if(window.hWin.HEURIST4.util.isempty(jqXHR.responseText)){
            
            //403 Forbidden
            
            if(jqXHR.status==500){
                err_message = 'Error_Server_Side';    
            }else{
                err_message = 'Error_Connection_Reset';    
            }
            console.error(err_message, url);
        }else{
            err_message = jqXHR.responseText;
        }
        return {
            status: window.hWin.ResponseStatus.UNKNOWN_ERROR,
            message: err_message,
            request_code: request_code
        };
        
    },
       
    //
    // we have to reduce the usage to minimum. Need to implement method in hapi via central controller
    // this method is used 
    // 1) for call H3 scripts in H4 code
    // 2) in case hapi is not inited 
    // 3) for third-party web services
    //
    sendRequest: function(url, request, caller, callback, dataType, timeout){
        
        let action = '';
        
        if(request){
        
            if(!request.db && window.hWin && window.hWin.HAPI4){
                request.db = window.hWin.HAPI4.database;
            }
            
            action = url.substring(url.lastIndexOf('/')+1);
            if(action.indexOf('.php')>0) {
                action = action.substring(0,action.indexOf('.php'));   
            }
        }
        
        let request_code = {script:action, action:''};
        
        //note jQuery ajax does not properly in the loop - success callback does not work often
        let options = {
            url: url,
            type: "POST",
            data: request,
            cache: false,
            error: function(jqXHR, textStatus, errorThrown ) {
                if(callback){
                    
                    let response = window.hWin.HEURIST4.util.interpretServerError(jqXHR, url, request_code);
                    
                    if(caller){
                        callback(caller, response);
                    }else{
                        callback(response);
                    }
                }
            },
            success: function( response, textStatus, jqXHR ){
                if(callback){
                    if(caller){

                        if($.isPlainObject(response)){
                            response.request_code = request_code;
                        }
                        callback(caller, response);
                    }else{
                        callback(response);    
                    }
                }
            },
            fail: function(  jqXHR, textStatus, errorThrown )
            {
                
                let response = window.hWin.HEURIST4.util.interpretServerError(jqXHR, url, request_code);
                
                if(callback){
                    if(caller){
                        callback(caller, response);
                    }else{
                        callback(response);    
                    }
                }
            }
        };

        if(window.hWin.HEURIST4.util.isnull(dataType)){
            options['dataType'] = 'json';
        }else if(dataType!='auto'){
            options['dataType'] = dataType;    
        }
        if(timeout>0){
            options['timeout'] = timeout;    
        }
        
        $.ajax(options);
    },

    
    //
    //
    //
    windowOpenInPost: function(actionUrl, windowName, windowFeatures, params) 
    {
        let mapForm = document.createElement("form");
        let milliseconds = new Date().getTime();
        windowName = windowName+milliseconds;
        mapForm.target = windowName;
        mapForm.method = "POST";
        mapForm.action = actionUrl;
        
        for (const key in params){
            let mapInput = document.createElement("input");
                mapInput.type = "hidden";
                mapInput.name = key;
                mapInput.value = params[key];
                mapForm.appendChild(mapInput);

        }
        document.body.appendChild(mapForm);

        let map = window.open('', windowName, windowFeatures);
        if (map) {
            mapForm.submit();
        } else {
            alert('You must allow popups for this map to work.');
        }
    },    
    
    getScrollBarWidth: function() {
        let $outer = $('<div>').css({visibility: 'hidden', width: 100, overflow: 'scroll'}).appendTo('body'),
            widthWithScroll = $('<div>').css({width: '100%'}).appendTo($outer).outerWidth();
        $outer.remove();
        return 100 - widthWithScroll;
    },

    //
    // Parse string date using Temporal library
    //
    parseDates: function(start, end){
        if(window['Temporal'] && (start || end)){   
            //Temporal.isValidFormat(start)){
            if(start==null && end!=null){
                start = end;
                end = null;
            }

            // for VISJS timeline - must be ISO string
            function __forVis(dt){
                if(dt){
                    if(!dt.getMonth()){
                        dt.setMonth(1)
                    }
                    if(!dt.getDay()){
                        dt.setDay(1)
                    }

                    let res = dt.toString('yyyy-MM-ddTHH:mm:ssz');
                    return res;
                }else{
                    return '';
                }

            }    


            try{
                let temporal;
                if(start!='' && typeof start === 'string'){

                    if(start.search(/VER=/)!==-1){
                        temporal = new Temporal(start);
                        if(temporal){
                            let dt = temporal.getTDate('TPQ');  
                            if(!dt) dt = temporal.getTDate('PDB'); //probable begin

                            if(dt){ //this is range - find end date
                                let dt2 = temporal.getTDate('TAQ'); 
                                if(!dt2) dt2 = temporal.getTDate('PDE'); //probable end
                                end = __forVis(dt2);
                            }else{
                                dt = temporal.getTDate('DAT');  //simple date
                            }

                            if(dt){
                                start = __forVis(dt);
                            }else{
                                return null;
                            }
                        }
                    }else{
                        start = __forVis(new TDate(start));
                    }
                }

                if(end!='' && typeof end === 'string') {
                    if(end.search(/VER=/)!==-1){
                        temporal = new Temporal(end);
                        if(temporal){
                            let dt = temporal.getTDate('TAQ'); 
                            if(!dt) dt = temporal.getTDate('PDE');//probable end
                            if(!dt) dt = temporal.getTDate('DAT');
                            end = __forVis(dt);
                        }
                    }else{
                        end = __forVis(new TDate(end));
                    }
                }
            }catch(e){
                return null;
            }
            return [start, end];
        }
        return null;
    },    

    //
    // Get CSS property value for a not yet applied class
    //
    getCSS: function (prop, fromClass) {
        let $inspector = $("<div>").css('display', 'none').addClass(fromClass);
        $("body").append($inspector); // add to DOM, in order to read the CSS property
        try {
            return $inspector.css(prop);
        } finally {
            $inspector.remove(); // and remove from DOM
        }
    },

    //
    //
    //
    cssToJson: function(css){

        let json = {};

        if(css){

            let styles = css.split(';'),
            i= styles.length,
            k, v;

            while (i--)
            {
                let pos = styles[i].indexOf(':');
                if(pos>1){
                    k = String(styles[i].substr(0,pos)).trim();
                    v = String(styles[i].substr(pos+1)).trim();
                }
                /*style = styles[i].split(':');
                k = String(style[0]).trim();
                v = String(style[1]).trim();*/
                if (k && v && k.length > 0 && v.length > 0)
                {
                    if(v==='true')v=true;
                    else if(v==='false')v=false;     
                    json[k] = v;
                }
            }
        }

        return json;        

    },


    /*: function(e){
    for(var r=0,i=0;i<e.length;i++){
    r=(r<<5)-r+e.charCodeAt(i),r&=r;   
    }
    return r
    },*/

    hashString: function(str) {

        let hash = 0, i, c;
        let strlen = str?str.length:0;
        if (strlen == 0) return hash;

        for (i = 0; i < strlen; i++) {
            c = str.charCodeAt(i);
            hash = ((hash<<5)-hash)+c;
            hash = hash & hash; // Convert to 32bit integer
        }
        return hash;    
    },

    formatFileSize: function (bytes) {
        if (typeof bytes !== 'number') {
            return '';
        }
        if (bytes >= 1000000000) {
            return (bytes / 1000000000).toFixed(2) + ' GB';
        }
        if (bytes >= 1000000) {
            return (bytes / 1000000).toFixed(2) + ' MB';
        }
        return (bytes / 1000).toFixed(2) + ' KB';
    },


    //
    // download given url as a file (repalcement of usage A)
    //
    downloadURL: function(url, callback) {
        let $idown = $('#idown');

        if ($idown.length==0) {
            $idown = $('<iframe>', { id:'idown' }).hide().appendTo('body');
        }
        if (window.hWin.HEURIST4.util.isFunction(callback)) {
            $idown.on('load', callback);   
        }
        $idown.attr('src',url);
    },

    //
    // download content of given element (for example text area) as a text file
    //
    downloadInnerHtml: function (filename, ele, mimeType) {

        let elHtml = $(ele).html();
        window.hWin.HEURIST4.util.downloadData(filename, elHtml, mimeType);
    }, 

    //
    // download some data locally
    //
    downloadData: function (filename, data, mimeType) {

        mimeType = mimeType || 'text/plain';
        let  content = 'data:' + mimeType  +  ';charset=utf-8,' + encodeURIComponent(data);

        let link = document.createElement("a");
        link.setAttribute('download', filename);
        link.setAttribute('href', content);
        if (window.webkitURL != null)
        {
            // Chrome allows the link to be clicked
            // without actually adding it to the DOM.
            link.click();
            link = null;
        }
        else
        {
            // Firefox requires the link to be added to the DOM
            // before it can be clicked.
            link.onclick = function(){ document.body.removeChild(link); link=null;} //destroy link
            link.style.display = "none";
            document.body.appendChild(link);
            link.click();
        }

    },    


    isRecordSet: function(recordset){
        return !window.hWin.HEURIST4.util.isnull(recordset) && window.hWin.HEURIST4.util.isFunction(recordset.isA) && recordset.isA("HRecordSet");   
    },

    random: function(){
       
       
        if(window.crypto){
            const typedArray = new Uint8Array(10);
            const randomValues = window.crypto.getRandomValues(typedArray);
            return randomValues.join('').substr(0,15);        
        }else{
            return ''+Math.floor(Date.now() * Math.random())
           
            //return Math.ceil( arng.quick() * 99999999 ); //1~87  
        }
        
    },

    //scan all frames of current window and return object by name
    findObjInFrame: function(name){

        let i, frames;
        frames = document.getElementsByTagName("iframe");
        for (i = 0; i < frames.length; ++i)
        {  
            if( !window.hWin.HEURIST4.util.isnull(frames[i]['contentWindow'][name])){
                return frames[i]['contentWindow'][name];
            }
        }
        return null;
    },

    getMediaServerFromURL:function(filename){
        filename = filename.toLowerCase();
        if(filename.indexOf('youtu.be')>=0 || filename.indexOf('youtube.com')>=0){
            return 'youtube';
        }else if(filename.indexOf('vimeo.com')>=0){
            return 'vimeo';
        }else if(filename.indexOf('soundcloud.com')>=0){
            return 'soundcloud';            
        }else{
            return null;
        }
    },

    getFileExtension:function(filename){
       
       
       
        if(filename){
            let res = filename.match(/\.([^\./\?]+)($|\?)/);
            return (res && res.length>1)?res[1]:'';
        }else{
            return '';
        }

    },

    //
    //
    //
    versionCompare: function(v1, v2, options) {
        // determines if the version in the cache (v1) is older than the version in configIni.php (v2)
        // used to detect change in version so that user is prompted to clear cache and reload
        // returns -1 if v1 is older, -2 v1 is newer, +1 if they are the same
        let lexicographical = options && options.lexicographical,
        zeroExtend = options && options.zeroExtend,
        v1parts = v1.split('.'),
        v2parts = v2.split('.');

        function isValidPart(x) {
            return (lexicographical ? /^\d+[A-Za-z]*$/ : /^\d+$/).test(x);
        }

        if (!v1parts.every(isValidPart) || !v2parts.every(isValidPart)) {
            return NaN;
        }

        if (zeroExtend) {
            while (v1parts.length < v2parts.length) v1parts.push("0");
            while (v2parts.length < v1parts.length) v2parts.push("0");
        }

        if (!lexicographical) {
            v1parts = v1parts.map(Number);
            v2parts = v2parts.map(Number);
        }

        let i = 0;
        for (; i < v1parts.length; ++i) {

            if (v1parts[i] == v2parts[i]) {
                continue; // sub elements are the same, continue compare
            }
            else if (v1parts[i] > v2parts[i] || window.hWin.HEURIST4.util.isnull(v2parts[i])) {
                return -2; // cached version is newer, we will still need to clear cache and reload
            }
            else {
                return -1; // cached version is older, we will need to clear cache and reload
            }
        }

        if (v2parts.length == i) {
            return 1; // versions are the saame
        }

        if (v1parts.length != v2parts.length) {
            return -1;
        }

        return 0;
    },

    uniqueArray: function(arr){

        let n = {},r=[];
        for(let i = 0; i < arr.length; i++) 
        {
            if($.isPlainObject(arr[i])){
                r.push(arr[i]);
            }else if (!n[arr[i]]) 
            {
                n[arr[i]] = true; 
                r.push(arr[i]); 
            }
        }
        return r;            
    },

    //not strict search - valuable for numeric vs string 
    findArrayIndex: function(elt, arr /*, from*/)
    {
        if( window.hWin.HEURIST4.util.isempty(arr) ) return -1;

        let len = arr.length;

        let from = Number(arguments[2]) || 0;
        from = (from < 0)
        ? Math.ceil(from)
        : Math.floor(from);
        if (from < 0)
            from += len;

        for (; from < len; from++)
        {
            if (from in arr &&
                arr[from] == elt)
                return from;
        }
        return -1;
    },

    //
    // assumed that sdate is in UTC
    //
    getTimeForLocalTimeZone: function (sdate){
        let date = new Date(sdate+"+00:00");
        return (''+date.getHours()).padStart(2, "0")
        +':'+(''+date.getMinutes()).padStart(2, "0")
        +':'+(''+date.getSeconds()).padStart(2, "0");
    },

    //
    //flflnaixr
    //
    copyStringToClipboard: function(string_to_copy) {
        function handler (event){
            event.clipboardData.setData('text/plain', string_to_copy);
            event.preventDefault();
            document.removeEventListener('copy', handler, true);
        }

        document.addEventListener('copy', handler, true);
        document.execCommand('copy');
    },

    //
    // Retrieve youtube id from url
    //
    get_youtube_id: function(url){

        let matches = url.match(/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/);

        return matches[1];
    },

    //
    // General merge sorting function
    // array => Array of items to be sorted
    // compare => Function used for comparing array indexes
    //
    merge_sort: function(array, compare){

        if(!Array.isArray(array) || array.length < 2){
            return array;
        }
        if(!compare){
            compare = (a, b) => {
                return a < b;
            };
        }else if(!window.hWin.HEURIST4.util.isFunction(compare)){
            return array;
        }

        let arr_len = array.length;
        let mid = Math.floor(arr_len / 2);

        let left_array = window.hWin.HEURIST4.util.merge_sort(array.slice(0, mid), compare);
        let right_array = window.hWin.HEURIST4.util.merge_sort(array.slice(mid), compare);

        let sorted_array = [];

        while(left_array.length != 0 && right_array.length != 0){
            if(compare(left_array[0], right_array[0])){
                sorted_array.push(left_array.shift());
            }else{
                sorted_array.push(right_array.shift());
            }
        }
        let results = sorted_array.concat(left_array.concat(right_array));

        return results;
    },
    
    //
    // Restore IMG url. Converts from relative path to current "pro"
    //
    restoreRelativeURL: function(ele)
    {
        
        let src = ele.getAttribute('src');
        let file_id = ele.getAttribute('data-id');
        let db = window.hWin.HAPI4.database;
        let extra_params = '';

        //extract params from image src and recreate new url pointed to  baseURL_pro
        if(!window.hWin.HEURIST4.util.isempty(src)){
            let query = src.slice(src.indexOf('?'));
            if(src.indexOf('file=') > 0){
                file_id = window.hWin.HEURIST4.util.getUrlParameter('file', query);
            }

            if(src.indexOf('embedplayer=') > 0){
                extra_params += '&embedplayer='+window.hWin.HEURIST4.util.getUrlParameter('embedplayer', query);
            }

            if(src.indexOf('fancybox=') > 0){
                extra_params += '&fancybox='+window.hWin.HEURIST4.util.getUrlParameter('fancybox', query);
            }else{
                extra_params += '&fancybox=1';
            }

            if(window.HAPI4.is_publish_mode){ // image for a webpage

                let webcached = window.hWin.HEURIST4.util.getUrlParameter('fullres', query);
                webcached = !webcached ? 0 : webcached;

                extra_params += `&fullres=${webcached}`; // look for scaled down image, when retrieving
            }

            if(src.indexOf('db=') > 0){
                db = window.hWin.HEURIST4.util.getUrlParameter('db', query);
            }
        }

        //yes this is link to registered image
        if(!window.hWin.HEURIST4.util.isempty(file_id) && !window.hWin.HEURIST4.util.isempty(db)){
            src = window.hWin.HAPI4.baseURL_pro + '?db=' + db + '&file=' + file_id + extra_params;
            ele.setAttribute('src', src);
        }else 
        if (!window.hWin.HEURIST4.util.isempty(src) 
            && (src.indexOf('./')==0 || src.indexOf('/')==0)){ //relative path
              src = window.hWin.HAPI4.baseURL + src.substring(src.indexOf('/')==0?1:2);
              ele.setAttribute('src', src);
        }        
    },
    
    base64ToBytes: function(base64) {
        const binString = atob(base64);
        return Uint8Array.from(binString, (m) => m.codePointAt(0));
    },

    bytesToBase64: function(bytes) {
        const binString = Array.from(bytes, (x) => String.fromCodePoint(x)).join("");
        return btoa(binString);
    },
    
    
    isBase64: function(str) {
          const notBase64 = /[^A-Z0-9+\/=]/i;
          
          if(typeof str === 'string'){
              const len = str.length;
              if (!len || len % 4 !== 0 || notBase64.test(str)) {
                return false;
              }
              const firstPaddingChar = str.indexOf('=');
              return firstPaddingChar === -1 ||
                firstPaddingChar === len - 1 ||
                (firstPaddingChar === len - 2 && str[len - 1] === '=');
          }else{
              return false;
          }
    },
    
    //constants for saved searches\
    _NAME: 0, 
    _QUERY: 1,
    _GRPID: 2
}//end util

window.Hul = window.hWin.HEURIST4.util;

//-------------------------------------------------------------

String.prototype.htmlEscape = function() {
    return this.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/'/g, "&#39;");
}
/*
String.prototype.htmlUnescape = function() {
    var e = document.createElement("textarea");
    e.innerHTML = this;
    // handle case of empty input
    return e.childNodes.length === 0 ? "" : e.childNodes[0].nodeValue;    
}
*/

String.prototype.capitalize = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}
String.prototype.lpad = function(padString, length) {
    let str = this;
    while (str.length < length)
        str = padString + str;
    return str;
}

if (!Array.prototype.indexOf)
{
    Array.prototype.indexOf = function(elt /*, from*/)
    {
        let len = this.length;

        let from = Number(arguments[1]) || 0;
        from = (from < 0)
        ? Math.ceil(from)
        : Math.floor(from);
        if (from < 0)
            from += len;

        for (; from < len; from++)
        {
            if (from in this &&
                this[from] === elt)
                return from;
        }
        return -1;
    };
}



/*
if (!Array.prototype.unique){

    Array.prototype.unique = function()
    {
        
        //return $.grep(this, function(el, index) {
       
       
        
        
            var n = {},r=[];
            for(var i = 0; i < this.length; i++) 
            {
                if (!n[this[i]]) 
                {
                    n[this[i]] = true; 
                    r.push(this[i]); 
                }
            }
            return r;        
    };
}
*/
}


$.getMultiScripts2 = function(arr, path) {
    
    return new Promise(function(_resolve, _reject){
    
        (async () => {
          for (const scr of arr) {
            await $.getScript((path||"") + scr);
          }
          
          _resolve();
        })()
        .catch((err) => {
            //console.log(err);            
            // Something went wrong
            _reject(err);
        });
    
    });
    
}

$.getMultiScripts = function(arr, path) {
    let _arr = $.map(arr, function(scr) {
        return $.getScript( (path||"") + scr );
    });

    _arr.push($.Deferred(function( deferred ){
        $( deferred.resolve );
    }));

    return $.when.apply($, _arr);
}

function tinymceURLConverter(url, node, on_save, name)
{
    if(url.indexOf(window.hWin.HAPI4.baseURL_pro)===0)
    {
        url = url.replace(window.hWin.HAPI4.baseURL_pro, './');
        
    }else if(url.indexOf(window.hWin.HAPI4.baseURL)===0)
    {
        url = url.replace(window.hWin.HAPI4.baseURL, './');
    }

    // Return URL
    return url;
}   
