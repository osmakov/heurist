<?php

/**
* verifyInstallation.php
* Verifies presence and correct versions for external JS components, Help, fiel directories and so forth
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';

$system = new hserv\System();

$sysadmin_pwd = USanitize::getAdminPwd();

if($system->verifyActionPassword( $sysadmin_pwd, $passwordForServerFunctions) ){
    include_once dirname(__FILE__).'/../../hclient/framecontent/infoPage.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Verify Heurist installation</title>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">

        <link rel="stylesheet" type="text/css" href="../../h4styles.css" />
        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            Table tr td {
                line-height:2em;
            }
            .statusCell{
                width:50px;
                display: table-cell;
            }
            .maskCell{
                width:550px;
                display: table-cell;
            }
            .errorCell{
                display: table-cell;
            }
            .valid{
                color:green;
            }
            .invalid{
                color:red;
            }

        </style>
    </head>

    <body class="popup">
        <div class="banner">
            <h2>Verify Heurist installation</h2>
        </div>

        <div id="page-inner" style="padding-left: 6px;">
            This function verifies the presence of required PHP extensions.
            <!--
            and the location and writeability of folders required by the software.<br>
            It assumes that all instances of Heurist are located in subdirectories
            in a common location (generally /var/www/html/HEURIST)<br>
            and point to the same Heurist filestore (generally .../HEURIST/HEURIST_FILESTORE,
            which is often a simlink to the real storage location).<br>
            TODO: Checks should include: external javascript functions; help system; root file upload directory; index.html in parent. <br>
            -->

            <br><br><h3>If running within Heurist please run this using the latest instance on your server</h3><br>
            If you use an older instance it may not pick up all requirements for the latest instance.<br>&nbsp;<br>

            <hr><br>

            <?php

            if (extension_loaded("gd")) {print "gd ok<br>";} else {print "gd MISSING<br>";}
            if (extension_loaded("pdo")) {print "pdo ok<br>";} else {print "pdo MISSING<br>";}
            if (extension_loaded("mbstring")) {print "php-mbstring ok<br>";} else {print "php-mbstring MISSING<br>";}

            if (extension_loaded("mysqli")) {print "mysqli ok<br>";} else {print "mysqli MISSING<br>";}
            if (extension_loaded("json")) {print "json ok<br>";} else {print "json MISSING<br>";}
            if (extension_loaded("session")) {print "session ok<br>";} else {print "session MISSING<br>";}
            if (extension_loaded("dom")) {print "dom ok<br>";} else {print "dom MISSING<br>";}
            if (extension_loaded("curl")) {print "curl ok<br>";} else {print "curl MISSING<br>";}
            if (extension_loaded("xsl")) {print "xsl ok<br>";} else {print "xsl MISSING<br>";}
            if (extension_loaded("simpleXML")) {print "simpleXML ok<br>";} else {print "simpleXML MISSING<br>";}
            if (extension_loaded("xml")) {print "xml ok<br>";} else {print "xml MISSING<br>";}

            if (extension_loaded("pcre")) {print "pcre ok<br>";} else {print "pcre MISSING<br>";}
            if (extension_loaded("filter")) {print "filter ok<br>";} else {print "filter MISSING<br>";}

            if (extension_loaded("zip")) {print "zip ok<br>";} else {print "zip MISSING<br>";}
            if (extension_loaded("bz2")) {print "bz2 ok<br>";} else {print "bz2 MISSING, optional, preferred for database archiving<br>";}


            if (extension_loaded("pdo_sqlite")) {print "pdo_sqlite ok<br>";} else {print "pdo_sqlite MISSING, optional, required for FAIMS<br>";}
            if (extension_loaded("exif")) {print "exif ok<br>";} else {print "exif MISSING, optional, required for image file indexing<br>";}

            if (extension_loaded("imagick")) {print "imagick ok<br>";} else {print "imagick MISSING, optional, required for image webcache and pdf thumbnails<br>";}

            print "<br><br><h3>All loaded extensions:</h3><br>";
            // TODO: write lsit of loaded extensions out neatly rather than dumping the array
            print_r(get_loaded_extensions());


            print "<br><hr><br><h3>MySQL database server</h3><br>";
            $mysqli = mysql__connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DB_PORT);
            if ( is_array($mysqli) ){
                //connection to server failed
                print error_Div($mysqli[1]);
            }else{
                $version = $mysqli->server_info;
                $vers = explode('.',$version);
                $vers = ($vers[0]>=5 && ($vers[0]>5 || $vers[1]>=5))?' OK'
                    :error_Div('it must be at least 5.5');
                printf("<br>Connection OK. Server version: %s\n", $version.$vers);
            }
            ?>

            <br><hr><br><h3>Paths</h3><br>
            <?php
                print $defaultRootFileUploadPath.'<br>';
                print $defaultRootFileUploadURL;
            ?>
            <br><br><hr><br>
            Verification complete. Please note any errors listed above.
        </div>
    </body>
</html>