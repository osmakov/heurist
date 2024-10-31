<?php

/**
* bookmarkletPopup.php: defines the popup which is inserted when the bookmarklet is activated
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
    
header(CTYPE_JS);
require_once dirname(__FILE__).'/../../autoload.php';
$system = new hserv\System();
if(!$system->init(@$_REQUEST['db'])){
    return;
}

//@todo move code from js to php
//@todo get rectype names and groups here
//@todo find possible bookmark id
?>
var Heurist = {

w: 370,
h: 240,

uriBase: "<?=HEURIST_BASE_URL ?>",
uriHost: "<?=HEURIST_SERVER_URL ?>/",
database:"<?=HEURIST_DBNAME?>",
init: function () {
    // toggle display if our div is already present in the DOM
    var e = document.getElementById('__heurist_bookmarklet_div');
    if (e) {
        if (e.style.display == 'none') {
            e.style.display = 'block';
            e.style.left = '30px';
            e.style.top = '30px';
            if (document.all) {
                e.style.left = (document.body.scrollLeft + 30) + 'px';
                e.style.top = (document.body.scrollTop + 30) + 'px';
                if (document.body.scrollLeft == 0  &&  document.body.scrollTop == 0) window.scrollTo(0, 0);
            }
        }
        else
            Heurist.close();
        return;
    }

    // add our style sheet
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.type = 'text/css';
    link.href = Heurist.uriBase +'import/bookmarklet/bookmarklet-popup.css';
    document.getElementsByTagName('head')[0].appendChild(link);

    // get record types
    var scr = document.createElement('script');
    scr.type = 'text/javascript';
    scr.src = Heurist.uriBase +'import/bookmarklet/getRectypesAsJSON.php?db=' + Heurist.database;
    document.getElementsByTagName('head')[0].appendChild(scr);

    /* get bkmk id if already bookmarked */
    scr = document.createElement('script');
    scr.type = 'text/javascript';
    scr.src = Heurist.uriBase +'import/bookmarklet/getRecordIDFromURL.php?db='+Heurist.database+'&url=' + Heurist.urlcleaner(encodeURIComponent(location.href));
    document.getElementsByTagName('head')[0].appendChild(scr);
},

render: function() {
    // create the div
    var d = document.createElement('div');
    d.className = 'heurist';
    d.id = '__heurist_bookmarklet_div';
    d.style.left = '30px';
    d.style.top = '30px';
    if (document.all) {
        d.style.left = (document.body.scrollLeft + 30) + 'px';
        d.style.top = (document.body.scrollTop + 30) + 'px';
    }


    // create a header bar
    var hdr = document.createElement('div');
    hdr.className = 'bookmarklet_header';
    hdr.onmousedown = Heurist.dragStart;
    d.appendChild(hdr);

    // 'close' button
    var s = document.createElement('div');
    s.className = 'bookmarklet_close';
    s.onclick = Heurist.close;
    hdr.appendChild(s);

    // heurist home page link
    var a = document.createElement('a');
    a.href = Heurist.uriBase;
    if (document.all) {
        i = document.createElement('img');
        i.src = Heurist.uriBase +'hclient/assets/branding/h4logo16.png';
        a.appendChild(i);
    }
    else
    a.innerHTML = '<img alt src="'+ Heurist.uriBase +'hclient/assets/branding/h4logo16.png">';
    a.className='imglnk';
    hdr.appendChild(a);
    s = document.createTextNode('Heurist bookmarklet');
    hdr.appendChild(s);


    // heurist bookmarklet
    var dd = document.createElement("div");
    dd.id = "topline";
    dd.innerHTML = (! HEURIST_url_bib_id ? "Highlight text that you want to include in your record,<br> then choose one of the following:" : "");
    d.appendChild(dd);

    var t = d.appendChild(document.createElement("table"));
    t.style.margin = "0";
    t.style.width = "100%";

    var tr = t.appendChild(document.createElement("tr"));
    var td = tr.appendChild(document.createElement("td"));

    if (HEURIST_url_bkmk_id) {  // The page is already bookmarked by the current user
        var nobr = td.appendChild(document.createElement("nobr"));
        nobr.appendChild(document.createTextNode("Page already in Heurist"));
        nobr.style.color = "green";
        td = tr.appendChild(document.createElement("td"));

        tr = t.appendChild(document.createElement("tr"));
        td = tr.appendChild(document.createElement("td"));
        nobr = td.appendChild(document.createElement("nobr"));
        nobr.appendChild(document.createTextNode("Bookmarked by you"));
        nobr.style.color = "green";

        tr = t.appendChild(document.createElement("tr"));
        td = tr.appendChild(document.createElement("td"));
        td.colSpan = "2";
        td.style.textAlign = "right";

        a = td.appendChild(document.createElement("a"));
        a.target = "_blank";
        a.href= Heurist.uriBase +'?fmt=edit&db='+Heurist.database+'&recID='+ HEURIST_url_bib_id;

        a.onclick = function() { Heurist.close() };
        a.innerHTML = "edit record";

        tr = t.appendChild(document.createElement("tr"));
        td = tr.appendChild(document.createElement("td"));
        td.colSpan = "2";
        td.style.height = "10px";

    } else if (HEURIST_url_bib_id) { // The page has been already in database
        var nobr = td.appendChild(document.createElement("nobr"));
        nobr.appendChild(document.createTextNode("Page already in Heurist"));
        nobr.style.color = "green";
        td = tr.appendChild(document.createElement("td"));

        tr = t.appendChild(document.createElement("tr"));
        td = tr.appendChild(document.createElement("td"));
        nobr = td.appendChild(document.createElement("nobr"));
        nobr.appendChild(document.createTextNode("Not yet bookmarked by you"));
        nobr.style.color = "red";

        td = tr.appendChild(document.createElement("td"));
        var button = document.createElement("input");
            button.type = "button";
            button.value = "Bookmark Record";
            button.onclick = function() {
                Heurist.doBookmark();
            };
        td.appendChild(button);

        tr = t.appendChild(document.createElement("tr"));
        td = tr.appendChild(document.createElement("td"));
        td.colSpan = "2";
        td.style.height = "30px";

    } else { // Page has never been bookmarked in this database

        // Add the page as the default type, Web page / Internet bookmark
        tr = t.appendChild(document.createElement("tr"));
        td = tr.appendChild(document.createElement("td"));
        button = document.createElement("input");
        button.type = "button";
        button.value = "Bookmark as web page";
        button.onclick = function() { Heurist.doBookmark();};
        td.appendChild(button);

        tr = t.appendChild(document.createElement("tr"));
        td = tr.appendChild(document.createElement("td"));
        td.colSpan = "2";
        td.style.height = "20px";
        td.innerHTML = "or: ";

        // specify rectype
        tr = t.appendChild(document.createElement("tr"));
        td = tr.appendChild(document.createElement("td"));
        td.appendChild(Heurist.renderrectypeSelect());
        var button = document.createElement("input");
        button.id = "add-as-type-button";
        button.type = "button";
        button.value = "Add";
        button.style.color = 'grey';
        button.disabled = true;
        button.onclick = function() {
            var r = document.getElementById("rectype-select").value;
            if (r) Heurist.doBookmark(r);
        };
        td.appendChild(button);

        tr = t.appendChild(document.createElement("tr"));
        td = tr.appendChild(document.createElement("td"));
        td.colSpan = "2";
         td.innerHTML = "<p class=\"prompt\">Text is copied to the scratchpad from where it can be dragged to other fields. DOI, ISBN, ISSN are extracted and inserted as fields.</p>"
    }

    // link importer
    var nobr = d.appendChild(document.createElement("div"));
    nobr.innerHTML = "Import links from this page";
    nobr.className = "bookmarklet_link_importer";
    var button = document.createElement("input");
    button.type = "button";
    button.value = "Get";
    button.style.marginLeft = "5px";
    button.onclick = function() {
        Heurist.close();
        var w = open(Heurist.uriBase +'import/hyperlinks/importHyperlinks.php?db='+Heurist.database+'&shortcut=' + encodeURIComponent(location.href));
        void(window.setTimeout("w.focus()",200));
        return false;
    }
    nobr.appendChild(button);

    // add our div to the document tree
    if (document.all)
        document.documentElement.childNodes[1].appendChild(d);
    else
        document.documentElement.appendChild(d);

    d.style.display = 'block';

    // IE doesn't understand position: fixed
    if (document.all) {
        d.style.position = 'absolute';
        // window.scrollTo(0,0);
        if (document.body.scrollLeft == 0  &&  document.body.scrollTop == 0) window.scrollTo(0, 0);
            // some sites have weird stuff going on that breaks scroll{Left,Top}
    }
    else {
        d.style.position = 'fixed';
    }
},

close: function () {
    Heurist.fade();
},

fade: function() {
    var e = document.getElementById('__heurist_bookmarklet_div');

    if (e.filters) {
        var o = parseInt(e.filters.alpha.opacity) - 10;
        e.filters.alpha.opacity = o;
    } else {
        var o = parseFloat(e.style.opacity) - 0.1;
        e.style.opacity = o;
    }
    if (o > 0)
        setTimeout(Heurist.fade,50);
    else {
        e.style.display = 'none';
        e.style.opacity = '1.0';
        if (e.filters) e.filters.alpha.opacity = 100;
    }
},

dragStart: function(e) {
    var d = document.getElementById('__heurist_bookmarklet_div');
    if (d.filters) d.filters.alpha.opacity = 75;
    else d.style.opacity = 0.75;
    window.startDragCoords = Heurist.getCoords(e);
    window.startDragPos = { left: parseInt(d.style.left), top: parseInt(d.style.top) };
    document.onmousemove = Heurist.dragMid;
    document.onmouseup = Heurist.dragEnd;
    document.onmousedown = null;
    return false;
},

dragMid: function(e) {
    var d = document.getElementById('__heurist_bookmarklet_div');
    var coords = Heurist.getCoords(e);
    d.style.left = (window.startDragPos.left + (coords.x - window.startDragCoords.x)) + 'px';
    d.style.top = (window.startDragPos.top + (coords.y - window.startDragCoords.y)) + 'px';
    return false;
},

dragEnd: function(e) {
    var d = document.getElementById('__heurist_bookmarklet_div');
    Heurist.dragMid(e);
    if (d.filters) d.filters.alpha.opacity = '100';
    else d.style.opacity = '1.0';
    document.onmouseup = null;
    document.onmousemove = null;
    return true;
},

getCoords: function(e) {
    if (! e) e = event;
    var pos = new Object();
    pos.x = 0;
    pos.y = 0;
    if (e.pageX  ||  e.pageY) {
        pos.x = e.pageX;
        pos.y = e.pageY;
    } else if (e.clientX  ||  e.clientY) {
        pos.x = e.clientX + document.body.scrollLeft;
        pos.y = e.clientY + document.body.scrollTop;
    }

    return pos;
},

urlcleaner: function(x) { return x.replace(/.C2.A0/gi,'\032');},

findFavicon: function() {
    try {
        var links = document.getElementsByTagName("link");
        for (var i=0; i < links.length; ++i) {
            var rel = (links[i].rel + "").toLowerCase();
            if ((rel === "shortcut icon" || rel === "icon")  &&  links[i].href) {
                if (links[i].href.match(/^http:/)) {    // absolute href
                    return links[i].href;
                }
                else if (links[i].href.match(/^\//)) {    // absolute path on server
                    return document.location.href.replace(/(^.......[^\/]*\/?).*/, "$1" + links[i].href);
                }
                else {    // relative path ... ummm ... take a stab
                    return document.location.href.replace(/[^\//]*$/, links[i].href);
                }
            }
        }
    } catch (e) { }
    return "";
},

//
//  main method - add new record with extracted from page values
//
doBookmark: function(rectype) {
    Heurist.close();
    var version='20060713';
    var findSelection = function(w) {
        try {
            return w.document.selection ? w.document.selection.createRange().text : (w.getSelection()+'');
        } catch (e) {
            return '';
        }
    };
    var url = location.href;
    var titl = document.title;
    var sel = findSelection(window);
    if (! sel  &&  frames) {
        for (i=0; i < frames.length; ++i) {
            sel = findSelection(frames[i]);
            if (sel) {break;}
        }
    }
    var favicon = Heurist.findFavicon();

    //see index.php  window.hWin.HEURIST4.ui.openRecordEdit
    var w = open(Heurist.uriBase +'?fmt=edit&db='+Heurist.database
                + '&t=' + Heurist.urlcleaner(encodeURIComponent(titl))
                + '&u=' + Heurist.urlcleaner(encodeURIComponent(url))
                + (sel?('&d=' + Heurist.urlcleaner(encodeURIComponent(sel))) : '')
                + (favicon? ('&f=' + encodeURIComponent(favicon)) : '')
                + (rectype ? '&rec_rectype=' + rectype : '')
                + '&version=' + version);

    void(window.setTimeout('window.focus()',200));
},


renderrectypeSelect: function(sel) {
    var sel = document.createElement('select');
    var i,grpID;
    sel.id = 'rectype-select';
    sel.onchange = function() {
        var btn = document.getElementById("add-as-type-button");
        btn.disabled = ! this.value;
        btn.style.color = this.value?'black':'grey';

    };
    sel.options[0] = new Option('Select type...', '');
    sel.options[0].selected = true;
    sel.options[0].disabled = true;

    for (grpID in HEURIST_rectypes.groups){
        var grp = document.createElement("optgroup");
            grp.label = HEURIST_rectypes.groups[grpID].name;
        sel.appendChild(grp);

        for (i in HEURIST_rectypes.groups[grpID].showTypes) {
            if(i>=0){
                var recID = HEURIST_rectypes.groups[grpID].showTypes[i];
                var name = HEURIST_rectypes.names[recID];
                var opt = new Option(name, recID);
                opt.innerHTML = '&nbsp;&nbsp;&nbsp;&nbsp;'+name;
                sel.appendChild( opt );

            }
        }
    }
    return sel;
}

};

var HEURIST_rectypesOnload = function() {
    Heurist.rectypesLoaded = true;
    if (Heurist.rectypesLoaded  &&  Heurist.urlBookmarkedLoaded)
        Heurist.render();
};

var HEURIST_urlBookmarkedOnload = function() {
    Heurist.urlBookmarkedLoaded = true;
    if (Heurist.rectypesLoaded  &&  Heurist.urlBookmarkedLoaded)
        Heurist.render();
};

Heurist.init();