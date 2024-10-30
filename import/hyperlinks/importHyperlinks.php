<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

define('LOGIN_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

use hserv\entity\DbUsrTags;

require_once dirname(__FILE__).'/../../hclient/framecontent/initPage.php';
require_once dirname(__FILE__).'/../../hserv/utilities/testSimilarURLs.php';
require_once dirname(__FILE__).'/../../hserv/records/edit/recordModify.php';


global $linkno, $disambiguate_rec_ids, $notes, $mysqli;

$nextmode = 'inputselect';
$mysqli = $system->get_mysqli();

if (@$_REQUEST['shortcut']) {

	$_REQUEST['mode'] = 'Analyse';
	$_REQUEST['source'] = 'url';
	$_REQUEST['url'] = $_REQUEST['shortcut'];

}

if (@$_REQUEST['old_srcname']){
	$srcname = $_REQUEST['old_srcname'];
}


if (@$_REQUEST['mode'] == 'Analyse') {
	if (@$_REQUEST['source'] == 'file') {
		$src = file_get_contents($_FILES['file']['tmp_name']);
		$srcname = $_FILES['file']['name'];
	} elseif (@$_REQUEST['source'] == 'url') {
		$_REQUEST['url'] = preg_replace('/#.*/', '', $_REQUEST['url']);

        $url = filter_input($_SERVER['REQUEST_METHOD']=='POST'?INPUT_POST:INPUT_GET, 'url', FILTER_VALIDATE_URL);

        if(empty($url) || $url===false){

            $src = null;
            $error = 'URL is not valid.';

        }else{
            $src = loadRemoteURLContentWithRange($url, null, false, 120);//load external webpage to extract links
            if(!$src){
                $error = 'URL could not be retrieved. Verify your proxy setting in configuration file.';
            }
        }
        if(!$src){
            $error = $error . ' <span style="font-weight: normal;">You might try saving the page you are importing, and then <a href="importHyperlinks.php">import from file</a>.</span>';
        }

		$srcname = @$_REQUEST['url'];
	}

	if (@$src) {
		$base_url = @$_REQUEST['url'];
		if (preg_match('!<base[^>]*href=["\']?([^"\'>\s]+)["\']?!is', $src, $url_match)){
			$base_url = $url_match[1];
        }
		$base_url_root = preg_replace('!([^:/])/.*!', '$1', $base_url);
		$base_url_base = preg_replace('!([^:/]/.*/)[^/]*$!', '$1', $base_url);
		if (substr($base_url_base, -1, 1) != '/') {$base_url_base = $base_url_base . '/';}

		// clean up the page a little
		$src = preg_replace('/<!-.*?->/s', '', $src);
		$src = preg_replace('!<script.*?</script>!is', '', $src);
		$src = preg_replace('!<style.*?</style>!is', '', $src);

		// find the page title
		preg_match('!<title>([^><]*)</title>!is', $src, $title_matches);
		if (@$title_matches[1]){
			$notes_src_str = " [source: '".$title_matches[1]."' (".$srcname.")]";
        }else{
			$notes_src_str = " [source: ".$srcname."]";
        }


		preg_match_all('!(<a[^>]*?href=["\']?([^"\'>\s]+)["\']?[^>]*?'.'>(.*?)</a>.*?)(?=<a\s|$)!is', $src, $matches);

		/* get a list of the link-texts that we are going to ignore */
		$ignored = mysql__select_assoc2($mysqli, 'SELECT lcase(hyf_String), -1 usrHyperlinkFilters '
                        .' WHERE hyf_UGrpID is null or hyf_UGrpID='.$system->get_user_id());

		$wildcard_ignored = array();
		if($ignored){
			foreach ($ignored as $key => $val) {
				$key_len = strlen($key);

				if (@$key[$key_len-1] == '*') {	/* wildcard at the end of the string only */
					unset($ignored[$key]);
					$wildcard_ignored[substr($key, 0, $key_len-1)] = $key_len - 1;
				}
			}
		}

        // minimum number of words that must appear in the link
		$word_limit = mysql__select_value($mysqli, 'select ugr_MinHyperlinkWords from sysUGrps where ugr_ID = '.$system->get_user_id());

		$urls = array();
		$notes = array();
		$last_url = '';
		for ($i=0; $i < count($matches[1]);++$i) {
			// ignore javascript links, mozilla 'about' links
			if (preg_match('!^(javascript|about):!i', @$matches[2][$i])) {
				continue;
            }

			if (! preg_match('!^[-+.a-z]+:!i', @$matches[2][$i])) {	/* doesn't start with protocol -- a relative URL */
				if (substr($matches[2][$i], 0, 1) == '/'){	/* starts with a slash -- relative to root */
					$matches[2][$i] = $base_url_root . $matches[2][$i];
				}else{
					$matches[2][$i] = $base_url_base . $matches[2][$i];
                }

				//while (preg_match('!/\\.\\.(?:/|$)!', $matches[2][$i]))	/* remove ..s */
					//$matches[2][$i] = preg_replace('!(http://.+)(?:/[^/]*)/\\.\\.(/|$)!', '\\1\\2', $matches[2][$i]);

			}

			$matches[3][$i] = trim(preg_replace('/\s+/', ' ', str_replace('&nbsp;', ' ', strip_tags($matches[3][$i]))));

			$lcase = strtolower(html_entity_decode($matches[3][$i]));

			$forbidden = 0;
			if (@$ignored[$lcase]){
                    $forbidden = 1;            // ignore forbidden links
            }
			else {
				foreach ($wildcard_ignored as $wc => $len) {
					if (substr($lcase, 0, $len) == $wc) {
                            $forbidden = 1; break;
                    }
				}
			}
			if (! @$forbidden  and  substr($matches[3][$i], 0, 5) != 'http:') {

				if (($word_limit and ! $matches[3][$i])
				 or (substr_count($matches[3][$i], ' ')+1 < $word_limit)) {
                    $forbidden = 1;    // ignore short links
                 }
			}
			if (@$forbidden) {
				if (@$last_url) {
                    $notes[$last_url] .= strip_tags(@$matches[1][$i]);
                }
				continue;
			}

			/* matches[2] contains the URLs, matches[3] contains the text of the link */
			if (! @$urls[$matches[2][$i]]  or  @$matches[2][$i] == @$urls[$matches[2][$i]]) {
				$url = html_entity_decode($matches[2][$i]);

				// if ($matches[2][$i] != $matches[3][$i])
					$urls[$url] = $matches[3][$i];
				/* REMOVED LIMITATION: if the text of the link is the URL itself, omit it */
				//	$urls[$matches[2][$i]] = ;	// continue;
				$notes[$url] = strip_tags($matches[1][$i]);
				$last_url = $url;
			}
		}

		foreach ($notes as $url => $val) {
			$val = preg_replace('/\s*(\n)\s*/s', "$1", $val);
			$notes[$url] = preg_replace('/[ 	]+/s', ' ', $val);
		}

		$nextmode = 'printurls';
	}

} elseif (@$_REQUEST['link']) {
	$urls = array();
	$max_no = max(array_keys($_REQUEST['link']));

	for ($i=1; $i <= $max_no; ++$i) {
		if ($_REQUEST['link'][$i] && filter_var($_REQUEST['link'][$i], FILTER_VALIDATE_URL)){
                $urls[filter_var($_REQUEST['link'][$i],FILTER_SANITIZE_URL)] = filter_var(@$_REQUEST['title'][$i]);
        }
	}

	$nextmode = 'printurls';
}


//print 'mode '.@$_REQUEST['mode'];
//print 'addtag '.@$_REQUEST['adding_tags'];
//print 'links '.@$_REQUEST['links'];


$disambiguate_rec_ids = array();
if ((@$_REQUEST['mode'] == 'Bookmark checked links'  ||  @$_REQUEST['adding_tags'])  &&  @$_REQUEST['links'])
{

	$record_tobebookmarked = array();

	foreach (@$_REQUEST['links'] as $linkno => $checked) {
		if (! @$checked) {continue;}

		$rec_id = records_check( @$_REQUEST['link'][$linkno], @$_REQUEST['title'][$linkno],
                                (@$_REQUEST['use_notes'][$linkno]? @$_REQUEST['notes'][$linkno] . @$notes_src_str : null),
                                 @$_REQUEST['rec_ID'][$linkno]);

		if ($rec_id && is_array($rec_id)) {
			// no exact match, just a list of nearby matches; get the user to select one
			$disambiguate_rec_ids[$_REQUEST['link'][$linkno]] = $rec_id;
			continue;
		}

		if (! @$rec_id) {continue;}	/* malformed URL */

		array_push($record_tobebookmarked, $rec_id);
	}

	if(!empty($record_tobebookmarked)){

		if (@$_REQUEST['adding_tags'] == 1) {
			$kwd = @$_REQUEST['wgTags'];
		} else {
			$kwd = @$_REQUEST['kwd'][$linkno];
		}

		//method to add bookmarks and tags
		$data = array();
		$data['rec_ids'] = $record_tobebookmarked;

        $params = array(
           'entity'=>'usrTags',
           'mode'  =>'assign',
           'tagIDs'=> $kwd,
           'recIDs'=> $record_tobebookmarked
        );

        $entity = new DbUsrTags($system, $params);
        $res = $entity->batch_action();

        if( is_bool($res) && !$res ){
            $error = $system->getError();
            $error = $error['message'];
        }else{
            $success = 'Bookmarks added: '.$res['bookmarks'];
        }

	}else{
		$error = "Nothing to bookmark. Select links";
	}


	/*
	$bkmk_insert_count = 0;
	if (bookmark_insert(@$_REQUEST['link'][$linkno], @$_REQUEST['title'][$linkno], $kwd, $rec_id)){
		++$bkmk_insert_count;
	}
	if (@$bkmk_insert_count == 1){
		$success = 'Added one bookmark';
	}elseif (@$bkmk_insert_count > 1){
		$success = 'Added ' . $bkmk_insert_count . ' bookmarks';
	}
	*/
}


// filter the URLs (get rid of the ones already bookmarked)
if (@$urls) {
    $bkmk_urls = mysql__select_assoc2($mysqli, 'SELECT rec_URL, 1 FROM usrBookmarks '
        .'left join Records on rec_ID = bkm_recID WHERE bkm_UGrpID='.$system->get_user_id());

	$ignore = array();
	foreach ($urls as $url => $title){
		if (@$bkmk_urls[$url]) {$ignore[$url] = 1;}
	}
}

?>
	<title>Import Hyperlinks</title>

    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css">

    <style type="text/css">
        body{
            font-family: Helvetica,Arial,sans-serif;
            line-height: 2ex;
        }

        .input-row {
            border-bottom: 1px solid RGBA(255,255,255,0.5);
            padding: 3px 0;
        }
        .input-row input:not([type="submit"]) {
            font-size: 11px;
            background: none repeat scroll 0 0 #FFF;
            border: 1px solid rgba(0, 0, 0, 0.2);
            margin: 2px 0;
            padding: 3px;
        }

		.input-header-cell {width:140px;min-width:140px;max-width:140px; vertical-align:baseline;}
		.input-header-cell input[type="radio"]{float:left;min-width:35px}
		.error {color:#C00; font-weight:bold;}
		.words {color: #6A7C99;}
		.similar_bm{text-align: left;width:100%;color: #6A7C99;}
		.similar_bm label{text-align: left;}

        H2 {
            color: #6A7C99;
            font-size: 14px;
            line-height: 25px;
            margin: 0;
        }
        A:link {
            color: #2080C0;
            text-decoration: none;
        }
        P {
            color: #333333;
            font-size: 11px;
            line-height: 21px;
        }

        .button, input[type="button"], input[type="submit"] {
            text-transform: uppercase !important;
            display: inline-block !important;
            color: #666;
            cursor: pointer !important;
            font-weight: bold !important;
            border: 1px solid RGBA(0,0,0,0.2) !important;
            padding: 0 3px;
            height: 20px !important;
            line-height: 19px !important;
            font-family:arial,helvetica,clean,sans-serif;
        }
        .button, input[type="button"]:hover, input[type="submit"]:hover {
            background-color: RGBA(0,0,0,0.1) !important;
            border: 1px solid RGBA(0,0,0,0.4) !important;
            box-shadow: 0 1px 2px RGBA(0,0,0,0.4) inset !important;
            text-decoration: none !important;
            color: #000 !important;
        }
	</style>

    <script type="text/javascript">
        function onPageInit(success){
                    $('input[type="button"]').button().css({'background-color':'#ddd','text-transform':'uppercase'});
/* adjust size in case of dialog
                    var body = document.body,
                        html = document.documentElement;

                    var desiredHeight = Math.max( body.scrollHeight, body.offsetHeight,
                                           html.clientHeight, html.scrollHeight, html.offsetHeight );
                    var desiredWidth = Math.max( 700, body.scrollWidth, body.offsetWidth,
                                           html.clientWidth, html.scrollWidth, html.offsetWidth );

                    if(typeof doDialogResize != 'undefined' && doDialogResize.call && doDialogResize.apply) {
                        doDialogResize(desiredWidth, desiredHeight);
                    }
*/
        }

    </script>
</head>


<body class="popup" width=600 height=400 style="margin:10px;">

<script src="importHyperlinks.js"></script>

<?php //this frame is needed for title lookup ?>
<form action="importHyperlinks.php?db=<?php echo HEURIST_DBNAME?>" method="post"
        enctype="multipart/form-data" name="mainform" id="mainform" style="margin: 0px 3px;">

<input type="hidden" name="wgTags" id="wgTags">
<input type="hidden" name="adding_tags" value="0" id="adding_tags_elt">
<input type="hidden" name="titlegrabber_lock">

<?php
	if ($nextmode == 'inputselect') {
?>

<p>You can import bookmarks from your web browser favourites (exporting bookmarks creates an html file)
or by capturing hyperlinks from a web page (such as a list of links, or a page containing some
hyperlinks of interest).</p>

<!--
<p><b>Firefox</b>: Bookmarks <b>&nbsp;&gt;&nbsp;</b> Manage Bookmarks <b>&nbsp;&gt;&nbsp;</b> File <b>&nbsp;&gt;&nbsp;</b> Export, writes <tt>bookmarks.html</tt> by default</p>
<p style="margin-left: 20px;" class="normal"><b>IE</b>: File <b>&nbsp;&gt;&nbsp;</b> Import and Export <b>&nbsp;&gt;&nbsp;</b> Export Favourites, writes <tt>bookmark.htm</tt> by default</p>
-->

<div class="separator_row" style="margin:20px 0 5px 0;"></div>

<?php		if (@$error) {	?>
 <div class="input-row"><div class="error"><?php echo  $error ?></div></div>
<?php		} ?>
 <div class="input-row">
        <div class="input-header-cell">
            <input type="radio" name="source" value="file" id="radio_file">Import from file:</div>
        <div class="input-cell">
            <input type="file" name="file" size="41" onChange="document.getElementById('radio_file').checked = 'checked';">
        </div>
 </div>
 <div class="input-row">
        <div class="input-header-cell">
            <input type="radio" name="source" value="url" id="radio_url">Import from URL:
        </div>
        <div class="input-cell">
            <input type="text" name="url" size="50" onChange="document.getElementById('radio_url').checked = 'checked';">
        </div>
 </div>
 <div class="input-row" style="text-align:center">
        <input type="submit" name="mode" value="Analyse">
 </div>

<?php
	} elseif($nextmode == 'printurls') {

/* removed by saw 2010/11/12 doesn't seemed to be used anymore
		$tags = mysql__select_array('usrTags', 'tag_Text', 'tag_UGrpID='.$system->get_user_id().' order by tag_Text');
		$tag_options = '';
		foreach ($tags as $kwd)
			$tag_options .= '<option value="'.htmlspecialchars($kwd).'">'.htmlspecialchars($kwd)."</option>\n";
*/

        $word_limit = mysql__select_value($mysqli, 'select ugr_MinHyperlinkWords from sysUGrps where ugr_ID = '.$system->get_user_id());
        $word_limit = intval($word_limit);
?>
<h2 style="padding-left: 20px;">Import Hyperlinks</h2>
<p style="padding-left: 20px;">
Web links in <b><?php echo  htmlspecialchars($srcname) ?></b>
<input type="hidden" name="old_srcname" value="<?php echo  htmlspecialchars($srcname) ?>">

Note: the list only shows links which you have not already bookmarked.<br>
<?php if ($word_limit) { ?>
  Only links with at least <?php echo  ($word_limit == 1)? 'one word' : $word_limit.' words' ?> are shown,
  and common
<?php } else { ?>Common<?php } ?>
  hyperlink texts are ignored.
  &nbsp;&nbsp;
  <input type="button"
    onClick="{'<?php echo HEURIST_BASE_URL;?>import/hyperlinks/configImportSettings.php?db=<?php echo HEURIST_DBNAME;?>',
  { title:'Bookmark import settings',
    width:700,
    height:400,
    callback: function( context ) {
        if(context){
            document.forms[0].style.display = 'none';
            document.location.reload();
        }
    } });}"
    value="Change settings">
<br>
We recommend bookmarking a few links at a time.<br>The list is reloaded after each addition and after change of settings.

<?php		if (@$error) {	?>
 <div class="input-row"><?php echo  $error ?></div>
<?php		} ?>
<?php		if (@$success) {	?>
 <div class="input-row" style="color:#0000ff;font-weight:bold;"><?php echo  htmlspecialchars($success) ?></div>
<?php		} ?>
<?php		if (@$disambiguate_rec_ids) { ?>
 <div class="input-row" style="color:blue;"">
  <b><?php echo  (count($disambiguate_rec_ids) == 1)? 'One of your selected links is' : 'Some of your selected links are' ?>
  similar to record(s) already in the database.</b><br>
  The similar records are shown below: please select the appropriate page, or add a new URL to the database.<br>
  Then click on "Bookmark checked links" again.
 </div>
<?php		} ?>
</p>
 <div class="input-row" style="padding-left: 20px;">
   <a href="#" onClick="checkAll(); return false;">Check all</a>
   &nbsp;&nbsp;
   <a href="#" onClick="unCheckAll(); return false;">Uncheck all</a>
   &nbsp;&nbsp;
   <input type="button" name="mode" value="Bookmark checked links" style="font-weight: bold;"
        onClick="{doBookmark();}">
 </div>



<?php
/* do two passes: first print any that need disambiguation, then do the rest */
		if (@$disambiguate_rec_ids) {
			$linkno = 0;
			foreach (@$urls as $url => $title) {
				++$linkno;
				if (! @$disambiguate_rec_ids[$url]) {continue;}

				print_link($url, $title);
				$ignore[$url] = 1;
			}

			print '<div class="input-row">&nbsp;</div>' . "\n";
		}

		$linkno = 0;
		foreach (@$urls as $url => $title) {
			++$linkno;
			if (@$ignore[$url]) {
				print '<input type="hidden" name="link['.$linkno.']" value="'.htmlspecialchars($url).'">';
				continue;	// already bookmarked; have to give the bogus element to keep PHP numbering in sync
			}

			print_link($url, $title);
		}

	}
?>
</form>

</body>
</html>
<?php
/* ----- END OF OUTPUT ----- */

// search for existing record by id or url. if not found add new one
function records_check($url, $title, $notes, $user_rec_id) {
	/*
	 * Look for a Records record corresponding to the given record;
	 * user_rec_id is the user's preference if there isn't an exact match.
	 * Insert one if it doesn't already exist;
	 * return the rec_ID, or 0 on failure.
	 * If there are a number of similar URLs, return a list of their rec_ids.
	 */
     global $system, $mysqli;

	// saw FIXME this should be
	$res = mysql__select_value($mysqli, 'select rec_ID from Records where rec_URL = "'
                .$mysqli->real_escape_string($url)
                .'" and (rec_OwnerUGrpID=0 or not rec_NonOwnerVisibility="hidden")');
	if ($res>0) {
		return $res;
	}

	if ($user_rec_id > 0) {
        $res = mysql__select_value($mysqli, 'select rec_ID from Records where rec_ID = "'
                .intval($user_rec_id)
                .'" and (rec_OwnerUGrpID=0 or not rec_NonOwnerVisibility="hidden")');
        if ($res>0) {
            return $res;
        }

	} elseif (! $user_rec_id) {

		$rec_ids = similar_urls($mysqli, $url);//see testSimilarURls
		if ($rec_ids) {return $rec_ids;}
/*
		$par_url = preg_replace('/[?].*'.'/', '', $url);
		if (substr($par_url, strlen($par_url)-1) == '/')	// ends in a slash; remove it
			$par_url = substr($par_url, 0, strlen($par_url)-1);

		$res = mysql_query('select rec_ID from Records where rec_URL like "'.mysql_real_escape_string($par_url).'%" and (rec_OwnerUGrpID=0 or not rec_NonOwnerVisibility="hidden")');
		if (mysql_num_rows($res) > 0) {
			$rec_ids = array();
			while ($row = mysql_fetch_row($res))
				array_push($rec_ids, $row[0]);
			return $rec_ids;
		}
*/
	}

    $system->defineConstants();

	// no similar URLs, no exactly matching URL, or user has explicitly selected "add new URL"
	//insert the main record
    $record = array();
    $record['ID'] = 0; //add new record
    $record['RecTypeID'] = RT_INTERNET_BOOKMARK;
    $record['AddedByImport'] = 1;
    $record['no_validation'] = true;
    $record['Title'] = $title;
    $record['URL'] = $url;
    $record['ScratchPad'] = $notes;
    $record['details'] = array();
    $record['details']["t:".DT_NAME] = array("0"=>$title);
    $record['details']["t:".DT_EXTENDED_DESCRIPTION] = array("0"=>$notes);


    $out = recordSave($system, $record);//see recordModify.php

    if ( @$out['status'] != HEURIST_OK ) {
        //print "<div style='color:red'> Error: ".$out["message"].DIV_E;
        return 0;
    }else{

        return intval($out['data']);
    }

}


function my_htmlspecialchars_decode($str) {
	return str_replace(array('&nbsp;', '&amp;', '&quot;', '&lt;', '&gt;', '&copy;'), array(' ', '&', '"', '<', '>', '(c)'), $str);
}

function print_link($url, $title) {
	global $linkno;
	global $disambiguate_rec_ids;
	global $notes;

    $url_visit = (strpos($url,'http://')===false)?'https://'.$url :$url;

    $title_esc = htmlspecialchars($title);
?>
<div class="input-row" style="background-color:#CCCCCC; padding-left: 40px; width:90%;">
		<input type="checkbox" name="links[<?php echo  $linkno ?>]" value="1" class="check_link" id="flag<?php echo $linkno ?>" <?php echo  @$_REQUEST['links'][$linkno]? 'checked' : '' ?>
            onChange="var t=document.getElementById('t<?php echo $linkno ?>').value; var n=document.getElementById('n<?php echo $linkno ?>').value; if (!this.checked || n.length > t.length) { var e=document.getElementById('un<?php echo $linkno ?>'); if(e) e.checked = this.checked; }">
		&nbsp;<input type="text" name="title[<?php echo  $linkno ?>]" value="<?php echo  $title_esc ?>" style="width:70%; font-weight: bold; background-color: #eee;" id="t<?php echo $linkno ?>">
		<input type="hidden" name="alt_title[<?php echo  $linkno ?>]" value="<?php echo  $title_esc ?>" id="at<?php echo $linkno ?>">
		<input type="hidden" name="link[<?php echo  $linkno ?>]" value="<?php echo  htmlspecialchars($url) ?>" id="u<?php echo $linkno ?>">

  		&nbsp;&#91;<a href="<?php echo  htmlspecialchars($url_visit) ?>" target="_blank" rel="noopener"><span class="button">Visit</span></a>&#93;&nbsp;
		<input type="button" style="padding-top: 2px;height:23px !important;font-weight:normal;font-size:1em;" name="lookup[<?php echo  $linkno ?>]" value="Lookup Title" title="Lookup title from URL"
			onClick="{lookup_revert(this, <?php echo  $linkno ?>);}" id="lu<?php echo $linkno ?>">
		<input type="hidden" name="kwd[<?php echo  $linkno ?>]" value="<?php echo  htmlspecialchars(@$_REQUEST['kwd'][$linkno]) ?>" id="key<?php echo $linkno ?>">
</div>

<div class="input-row" style="padding-left: 60px;">
  <a target=_blank href="<?php echo  htmlspecialchars($url_visit) ?>" rel="noopener"><?php echo  htmlspecialchars($url) ?></a>
</div>
<div class="input-row" style="padding-left: 60px;">
	<div style="display:inline-block;width:30px;vertical-align: middle;">
		<input style="margin: 0px;" type="checkbox" name="use_notes[<?php echo  $linkno ?>]" value="1" id="un<?php echo $linkno ?>" class="use_notes_checkbox" title="Use Notes">
      	<input type="hidden" name="notes[<?php echo  $linkno ?>]" id="n<?php echo $linkno ?>"
            value="<?php echo  htmlspecialchars(@$_REQUEST['notes'][$linkno]? str_replace('"', '\\"', $_REQUEST['notes'][$linkno]) : str_replace('"', '\\"', $notes[$url]));?>">
	  </div>
      <div style="display:inline-block;width:70%;max-height:5.5em;text-overflow: ellipsis; overflow:hidden; white-space:normal;">
            <?php echo  htmlspecialchars(@$_REQUEST['notes'][$linkno]? $_REQUEST['notes'][$linkno] : wordwrap($notes[$url], 50, "\n", true)) ?>
      </div>
      <small class="words">
<?php
	if (@$_REQUEST['notes'][$linkno]){
		$word_count = intval(str_word_count($_REQUEST['notes'][$linkno]));
	}else{
		$word_count = str_word_count($notes[$url]);
    }

	if ($word_count == 1) {
		print '1 word';
	} elseif($word_count > 1) {
		print "$word_count words";
	}
?></small>
</div>

<?php
	if (@$disambiguate_rec_ids[$url]) {
?>
<div class="input-row">
	<div class="similar_bm">
		<span>
			<input type="radio" name="rec_ID[<?php echo  $linkno ?>]" value="-1" checked="checked" onchange="selectExistingLink(<?php echo  $linkno ?>);">
			<b>New (add this URL to the database)</b>
		</span>
	</div>

<?php
        $all_bibs = mysql__select_all($mysqli, 'SELECT rec_ID, rec_Title, rec_URL FROM Records '
                    .'where rec_ID in (' . join(',', $disambiguate_rec_ids[$url]) . ')', 1);

		foreach ($disambiguate_rec_ids[$url] as $rec_id) {
			$row = [$rec_id];
?>
	<div class="similar_bm">
		<span>
			<input type="radio" name="rec_ID[<?php echo  $linkno ?>]" value="<?php echo intval($rec_id);?>" onchange="selectExistingLink(<?php echo  $linkno ?>);">
			<?php echo htmlspecialchars($row[0]);//'rec_Title' ?>
		</span>&nbsp;&nbsp;
		<a style ="font-size: 80%; text-decoration:none;" target="_testwindow" href="<?php echo  htmlspecialchars($row[1]) ?>"><?php
				if (strlen($row[1]) < 100){ //'rec_URL'
					print htmlspecialchars(common_substring($row[1], $url));
				}else{
					print htmlspecialchars(common_substring(substr($row[1], 0, 90) . '...', $url));
                }
		?></a><br>
	</div>

<?php
		}
?>
  </div>
<?php
	}
?>
 <div class="input-row">&nbsp;</div>
<?php
}


function common_substring($url, $base_url) {
	for ($i=0; $i < strlen($url) && $i < strlen($base_url);++$i) {
		if ($url[$i] != $base_url[$i]) {break;}
	}

	return '<span style="color: black;">'.htmlspecialchars(substr($url, 0, $i)).'</span>'.htmlspecialchars(substr($url, $i));
}
?>