<!DOCTYPE html>
<?php

    /**
    *  Standalone record edit page. It may be used separately or within widget (in iframe)
    *
    *  Paramters
    *  q or recID - edit set of records defined by q(uery) or one record defiend by recID
    *
    *  otherwise it adds new record with
    *  rec_rectype, rec_owner, rec_visibility, tag, t -  title, u - url, d - description
    *  visgroups - csv group ids if rec_visibility viewable
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
use hserv\structure\ConceptCode;

require_once 'initPage.php';
require_once dirname(__FILE__).'/../../hserv/utilities/testSimilarURLs.php';

    $params = array();

if(@$_REQUEST['annotationId'] || @$_REQUEST['a']){

    $system->defineConstant('DT_ORIGINAL_RECORD_ID');

    $uuid = (@$_REQUEST['annotationId'])?$_REQUEST['annotationId']:$_REQUEST['a'];

    $mysqli = $system->get_mysqli();

    $res = mysql__select_row($mysqli, 'select dtl_RecID from recDetails '
        .' WHERE dtl_DetailTypeID='.DT_ORIGINAL_RECORD_ID .' AND dtl_Value="'.$mysqli->real_escape_string($uuid).'"');

    if ($res && $res[0] > 0) {
        $params = array('recID'=>intval($res[0]));
    }else{
        //annotation not found

    }


}elseif(@$_REQUEST['u']){
//this is an addition/bookmark of URL - at the moment from bookmarklet only

    $url = filter_var($_REQUEST['u'],FILTER_SANITIZE_URL);

// 1. check that this url already exists and bookmarked by current user  ------------------------
//      (that's double precaution - it is already checked in bookmarkletPopup)

    //  fix url to be complete with protocol and remove any trailing slash
    if (! preg_match('!^[a-z]+:!i', $url)) {$url = 'https://' . $url;}
    if (substr($url, -1) == '/') {$url = substr($url, 0, strlen($url)-1);}

    $mysqli = $system->get_mysqli();

    // look up the user's bookmark (usrBookmarks) table, see if they've already got this URL bookmarked -- if so, just edit it
    $res = mysql__select_row($mysqli, 'select bkm_ID, rec_ID from usrBookmarks left join Records on rec_ID=bkm_recID '
                .'where bkm_UGrpID="'.$system->get_user_id().'" '
                .' and (rec_URL="'.$mysqli->real_escape_string($url).'" or rec_URL="'.$mysqli->real_escape_string($url).'/")');

    if ($res && $res[1] > 0) { //already bookmarked
        $params = array('recID'=>$res[1]);

    }elseif (false && exist_similar($mysqli, $url)) {  //@todo implement disambiguation dialog
//----- 2. find similar url - show disambiguation dialog -----------------------------------------

        //redirect to disambiguation

        exit;
    }else{
// 3. otherwise prepare description and write parameters as json array in header of this page

//u - url
//t - record title
//d - selected text
//f - favicon
//rec_rectype




        $rec_rectype = @$_REQUEST['rec_rectype'];
        if($rec_rectype!=null){
            $rec_rectype = ConceptCode::getRecTypeLocalID($rec_rectype);
            $params['rec_rectype'] = $rec_rectype;
        }
        if(@$_REQUEST['t']){
            $params['t'] = $_REQUEST['t'];
        }
        if(@$_REQUEST['u']){
            $params['u'] = $url;
        }
        if(@$_REQUEST['f']){ //favicon

        }

        // preprocess any description
        if (@$_REQUEST['d']) {
            $description = $_REQUEST['d'];

        // use UNIX-style lines
            $description = str_replace("\r\n", "\n", $description);
            $description = str_replace("\r", "\n", $description);

        // liposuction away those unsightly double, triple and quadruple spaces
            $description = preg_replace('/ +/', ' ', $description);

        // trim() each line
            $regex_trim_all_spaces_except_eol = '/(?:^[ \t\v\f]+)|(?:[ \t\v\f]+$)/m';  //except \r\n
            $description = preg_replace($regex_trim_all_spaces_except_eol, '', $description);
            //single line - remove \r\n at the begin and end
            $description = preg_replace('/(?:^\s+)|(?:\s+$)/s', '', $description);

        // reduce anything more than two newlines in a row
            $description = preg_replace("/\n\n\n+/s", "\n\n", $description);

            if (@$_REQUEST['version']) {
                $description .= ' [source: web page ' . date('Y-m-d') . ']';
            }

            $params['d'] = $description;

            // extract all id from descriptions for bibliographic references
            $dois = array();
            if (preg_match_all('!DOI:\s*(10\.[-a-zA-Z.0-9]+/\S+)!i', $description, $matches, PREG_PATTERN_ORDER)){
                $dois = array_unique($matches[1]);
            }

            $isbns = array();
            if (preg_match_all('!ISBN(?:-?1[03])?[^a-z]*?(97[89][-0-9]{9,13}[0-9]|[0-9][-0-9]{7,10}[0-9X])\\b!i', $description, $matches, PREG_PATTERN_ORDER)) {
                $isbns = array_unique($matches[1]);
                if (!($rec_rectype>0) && defined('RT_BOOK')) {
                    $params['rec_rectype'] = RT_BOOK;
                }
            }

            $issns = array();
            if (preg_match_all('!ISSN(?:-?1[03])?[^a-z]*?([0-9]{4}-?[0-9]{3}[0-9X])!i', $description, $matches, PREG_PATTERN_ORDER)) {
                $issns = array_unique($matches[1]);
                if (!($rec_rectype>0) && defined('RT_JOURNAL_ARTICLE')){
                    $params['rec_rectype'] = RT_JOURNAL_ARTICLE;
                }
            }

        }

        if(!($params['rec_rectype']>0)){
           if(defined('RT_INTERNET_BOOKMARK')) {
                $params['rec_rectype']  = RT_INTERNET_BOOKMARK;
           }elseif(defined('RT_NOTE')) {
               $params['rec_rectype']  = RT_NOTE;
           }
        }
    }

}
else{
    $params = array();

    $rec_rectype = @$_REQUEST['rec_rectype'];
    if($rec_rectype!=null){
        $rec_rectype = ConceptCode::getRecTypeLocalID($rec_rectype);
        $params['rec_rectype'] = $rec_rectype;
    }elseif(intval(@$_REQUEST['recID'])>0){
        $params['recID'] = intval($_REQUEST['recID']);
    }
}

$params['guest_data'] = (@$_REQUEST['guest_data']==1);

print '<script>var prepared_params = '.json_encode($params).';</script>';

?>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/ui.tabs.paging.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.layout.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectFile.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>


        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageUsrTags.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchUsrTags.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/mediaViewer.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/baseAction.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAction.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAccess.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editorCodeMirror.js"></script>
        <link rel="stylesheet" href="<?php echo PDIR;?>external/codemirror-5.61.0/lib/codemirror.css">

        <script type="text/javascript">
            var $container;
            // Callback function on page initialization
            function onPageInit(success){
                if(success){

                    //FORCE LOGIN
                    if(!window.hWin.HEURIST4.ui.checkAndLogin(true, function(){ onPageInit(true);}))
                    {
                        return;
                    }

                    $container = $('<div>').appendTo($("body"));

                    var isPopup = (window.hWin.HEURIST4.util.getUrlParameter('popup', window.location.search)==1);

                    function __param(pname){
                        //in case of bookmarklet or annotation addition url parameters may be parsed and prepared
                        if($.isEmptyObject(prepared_params) ||
                           window.hWin.HEURIST4.util.isempty(prepared_params[pname]))
                        {
                                return window.hWin.HEURIST4.util.getUrlParameter(pname, window.location.search);
                        }else{
                                return prepared_params[pname];
                        }
                    }

                    //some values for new record can be passed as url parameters
                    var rec_rectype = __param('rec_rectype');
                    var new_record_params = {};
                    if(rec_rectype>0){
                        new_record_params['RecTypeID'] = rec_rectype;
                        new_record_params['OwnerUGrpID'] = __param('rec_owner');
                        new_record_params['NonOwnerVisibility'] = __param('rec_visibility');
                        new_record_params['NonOwnerVisibilityGroups'] = __param('visgroups');
                        new_record_params['tag'] = __param('tag');

                        new_record_params['Title'] = __param('t');
                        new_record_params['URL']   = __param('u');
                        new_record_params['ScratchPad']   = __param('d');

                        /*
                        $details = array();
                        new_record_params['title'] = __param('d');
                        new_record_params['title'] = __param('f');//favicon

                        if(!empty($details))
                            new_record_params['details'] = $details;
                        */

                    }

                    //hidden result list, inline edit form
                    var options = {
                        select_mode: 'manager',
                        edit_mode: 'editonly',
                        in_popup_dialog: isPopup,
                        new_record_params: new_record_params,
                        layout_mode:'<div class="ent_wrapper editor">'
                            + '<div class="ent_content_full recordList"  style="display:none;"/>'

                            + '<div class="ent_header editHeader"></div>'
                            + '<div class="editFormDialog ent_content">'
                                    + '<div class="ui-layout-west"><div class="editStructure treeview_with_header" style="background:white">'       +'</div></div>' //container for rts_editor
                                    + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                    + '<div class="ui-layout-east"><div class="editFormSummary">....</div></div>'
                                    //+ '<div class="ui-layout-south><div class="editForm-toolbar"/></div>'
                            + '</div>'
                            + '<div class="ent_footer editForm-toolbar"/>'
                        +'</div>',
                        onInitFinished:function(){

                            var q = __param('q');
                            var recID = __param('recID');

                            if(!q && recID>0){
                                q = 'ids:'+recID;
                            }

                            if(q){

                                window.hWin.HAPI4.RecordMgr.search({q: q, w: "e",  //all records including temp
                                                limit: 100,
                                                needall: 1, //it means return all recors - no limits
                                                detail: 'ids'},
                                function( response ){

                                    if(response.status == window.hWin.ResponseStatus.OK){

                                        var recset = new HRecordSet(response.data);
                                        if(recset.length()>0){
                                            $container.manageRecords('updateRecordList', null, {recordset:recset});
                                            $container.manageRecords('addEditRecord', recset.getOrder()[0]);

                                        }else{ // if(isPopup){

                                            var sMsg = ' does not exist in database or has status "hidden" for non owners';
                                            if(recID>0){
                                                sMsg = 'Record id#'+recID + sMsg;
                                            }else{
                                                sMsg = 'Record '+ sMsg;
                                            }

                                            window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null,
                                                {ok:'Close', title:'Record not found or hidden'},
                                                    {close:function(){ window.close();}});

                                        }
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response, false,
                                            {close:function(){ if(isPopup){ window.close();}}});
                                    }

                                });

                            }else{

                                $container.manageRecords('addEditRecord',-1);//call widget method
                            }

                        }
                    }

                    $container.manageRecords( options ).addClass('ui-widget');
                }
            }

            function onBeforeClose(){
                $container.manageRecords('saveUiPreferences');
            }
        </script>
    </head>
    <body>

    </body>
</html>
