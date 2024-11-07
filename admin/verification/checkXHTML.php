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

define('MANAGER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

$mysqli = $system->get_mysqli();

$woots = array();
$res = $mysqli->query("select * from woots");
if($res){
    while ($row = $res->fetch_assoc()) {
        array_push($woots, $row);
    }
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Check Wysiwyg Texts</title>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>

    <body class="popup">
        <div class="banner"><h2>Check Wysiwyg Texts</h2></div>

        <div id="page-inner" style="overflow:auto;padding-left: 6px;">
            <div>This function checks the WYSIWYG text data (personal and public notes, blog posts) for invalid XHTML<br>&nbsp;<hr></div>

            <table class="wysiwygCheckTable" role="presentation">
                <?php

                foreach ($woots as $woot) {
                    $valid = true;
                    $errs = array();



                    $res = $mysqli->query("select * from woot_Chunks where chunk_WootID = " . intval($woot["woot_ID"]) . " and chunk_IsLatest and not chunk_Deleted");
                    if($res){
                        while ($row = $res->fetch_assoc()) {
                            $err = check($row["chunk_Text"]);
                            if ($err) {
                                $valid = false;
                                array_push($errs, $err);
                            }
                        }
                        $res->close();
                    }

                    if ($valid) {

                    } else {
                        print "<tr><td><a target=_blank href='".HEURIST_BASE_URL."records/woot/woot.html?db=".HEURIST_DBNAME."w=";
                        print $woot["woot_Title"] . "'>";
                        print $woot["woot_Title"];
                        print "</a></td>\n";

                        print "<td>" . htmlspecialchars(join("\n", $errs)) . "s</td></tr>\n";
                    }
                }

                function check($html) {

                    $descriptorspec = array(
                        0 => array("pipe", "r"),
                        2 => array("pipe", "w"),
                    );
                    $proc = proc_open("xmllint -o /dev/null -", $descriptorspec, $pipes);

                    fwrite($pipes[0], "<html>" . $html . "</html>");
                    fclose($pipes[0]);

                    $out = stream_get_contents($pipes[2]);
                    fclose($pipes[2]);

                    $rv = proc_close($proc);



                    if ($rv != 0) {
                        return $out;
                    } else {
                        return 0;
                    }
                }

                ?>
            </table>

            <p>&nbsp;</p>
            <p>
                [end of check]
            </p>
        </div>
    </body>
</html>