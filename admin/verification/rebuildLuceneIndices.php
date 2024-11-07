<?php

    /**
    * rebuildLuceneIndices.php: Rebuilds all Lucence (Elastic Search) indices for the database
    *
    * from admin menu
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

    define('PDIR','../../');//need for proper path to js and css

    require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
    require_once dirname(__FILE__).'/../../hserv/records/indexing/elasticSearch.php';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Check Invalid Characters</title>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>

    <body class="popup">
        <div class="banner"><h2>Rebuilding Lucene indices for all tables</h2></div>
        <div id="page-inner" style="overflow:auto;padding-left: 6px;">
            <div>
                This function rebuilds lucene indices
                <br>&nbsp;<hr>
            </div>
<?php

    $code = ElasticSearch::buildAllIndices(HEURIST_DBNAME);
    if ($code ==0) {
        print '<div>Database indices have been rebuilt, please check for errors above</div>';
    } else {
        print '<div class="ui-state-error">Failed to rebuild indices, please '.CONTACT_HEURIST_TEAM.' (error code: '.$code.')</div>';
    }
?>
    </body>
</html>
