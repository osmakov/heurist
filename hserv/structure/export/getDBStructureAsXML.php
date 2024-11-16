<?php

/**
* getDBStructureAsXML.php: returns database definitions (rectypes, details etc.) as XML (HML)
*
* @param includeUgrps=1 will output user and group information in addition to definitions
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php';

header("Content-Type: application/xml");

// Normally jsut outputs definitions, this will include users/groups
$includeUgrps = @$_REQUEST["includeUgrps"];// returns null if not set

$sysinfo = $system->settings->get();
$db_version = $sysinfo['sys_dbVersion'].'.'.$sysinfo['sys_dbSubVersion'].'.'.$sysinfo['sys_dbSubSubVersion'];

define('HEURIST_DBID', $system->settings->get('sys_dbRegisteredID'));

$mysqli = $system->get_mysqli();

$rty_ID = @$_REQUEST["rty"];
$dty_ID = @$_REQUEST["dty"];
$trm_ID = @$_REQUEST["trm"];
if($rty_ID!=null){
    $rty_ID = intval(ConceptCode::getRecTypeLocalID($rty_ID));
}
if($dty_ID!=null){
    $dty_ID = intval(ConceptCode::getDetailTypeLocalID($dty_ID));
}
if($trm_ID!=null){
    $trm_ID = intval(ConceptCode::getTermLocalID($trm_ID));
}
$is_subset = ($rty_ID>0 || $dty_ID>0 || $trm_ID>0);


// * IMPORTANT *
// UPDATE THE FOLLOWING WHEN DATABASE FORMAT IS CHANGED:
// Version info in common/config/initialise.php
// admin/setup/dbcreate/blankDBStructure.sql - dump structure of hdb_HeuristCoreDefinitions database and insert where indicated in file
// admin/setup/dbcreate/coreDefinitions.txt (get this from the admin interface lsiting in exchange format)
// admin/setup/dbcreate/coreDefinitionsHuNI.txt (get this from the admin interface lsiting in exchange format)
// admin/setup/dbcreate/coreDefinitionsFAIMS.txt (get this from the admin interface lsiting in exchange format)


print XML_HEADER;
print "\n\n<hml_structure>";

// TODO: ADD OTHER XML HEADER INFORMATION *************

// File headers to explain what the listing represents and for version checking
print "\n\n<!--Heurist Definitions Exchange File, generated: ".date("d M Y @ H:i")."-->";
print "\n<HeuristBaseURL>" . HEURIST_BASE_URL. "</HeuristBaseURL>";
print "\n<HeuristDBName>" . HEURIST_DBNAME . "</HeuristDBName>";
print "\n<HeuristProgVersion>".HEURIST_VERSION."</HeuristProgVersion>";

// *** MOST IMPORTANT ***
// ** Check this on structure import to make sure versions match **
// However use of XML tags should allow import even if structure has evolved
print "\n<HeuristDBVersion>".$db_version."</HeuristDBVersion>";


// TODO: Also need to output general properties of the database set in Structure > Properties / dvanced Properties


if(!$is_subset){
// Output each of the definition tables in turn

// ------------------------------------------------------------------------------------------
// defRecTypeGroups

do_print_table2('defRecTypeGroups');


// ------------------------------------------------------------------------------------------
// defDetailTypeGroups

do_print_table2('defDetailTypeGroups');

// ------------------------------------------------------------------------------------------
// defVocabularyGroups

do_print_table2('defVocabularyGroups');

// ------------------------------------------------------------------------------------------
// Detail Type ONTOLOGIES

do_print_table2('defOntologies');

}
// ------------------------------------------------------------------------------------------
// Detail Type TERMS
if(!$is_subset || $trm_ID>0){

    do_print_table2('defTerms', $trm_ID);
}
// ------------------------------------------------------------------------------------------
// RECORD TYPES (this will be repeated for each of the tables)
if(!$is_subset || $rty_ID>0){

    do_print_table2('defRecTypes', $rty_ID);
}
// ------------------------------------------------------------------------------------------
// DETAIL TYPES
if(!$is_subset || $dty_ID>0){

    do_print_table2('defDetailTypes', $dty_ID);
}
// ------------------------------------------------------------------------------------------
// RECORD STRUCTURE
if(!$is_subset || $rty_ID>0){

    do_print_table2('defRecStructure', $rty_ID);

}

if(!$is_subset){

// ------------------------------------------------------------------------------------------
// RELATIONSHIP CONSTRAINTS

do_print_table2('defRelationshipConstraints');

// ------------------------------------------------------------------------------------------
// defFileExtToMimetype

do_print_table2('defFileExtToMimetype');

// ------------------------------------------------------------------------------------------
// defTranslations

do_print_table2('defTranslations');

// ------------------------------------------------------------------------------------------
// sysDashboard

do_print_table2('sysDashboard');

// ------------------------------------------------------------------------------------------
// defCalcFunctions

do_print_table2('defCalcFunctions');

// ------------------------------------------------------------------------------------------
// defCrosswalk

do_print_table2('defCrosswalk');

// ------------------------------------------------------------------------------------------
// defURLPrefixes

do_print_table2('defURLPrefixes');

}
// ------------------------------------------------------------------------------------------
// Output the following only if parameter switch set and user is an admin

if (!$includeUgrps) {
    print "\n\n<!-- User and group information not requested -->";
    print "\n\n</hml_structure>";
    return;
}

if (! $system->is_admin() ) {
    print "\n\n<!-- You do not have sufficient privileges to list users and groups -->";
    print "\n</hml_structure>";
    return;
}
if(!$is_subset){

// ------------------------------------------------------------------------------------------
// sysUGrps

do_print_table2('sysUGrps');

// ------------------------------------------------------------------------------------------
// sysUsrGrpLinks

do_print_table2('sysUsrGrpLinks');

// ------------------------------------------------------------------------------------------
// usrHyperlinkFilters

do_print_table2('usrHyperlinkFilters');

// ------------------------------------------------------------------------------------------
// usrTags

do_print_table2('usrTags');

}

print "\n</hml_structure>";// end of file


//
//
//
function do_print_table2( $tname, $id=0 )
{
    global $mysqli;

    $tname_tag = substr($tname,3);

    print "\n\n<$tname_tag>";


    $flds_list = mysql__select_assoc2($mysqli, 'SHOW COLUMNS FROM '.$tname);
    $flds_names = array_keys($flds_list);
    $flds = '`'.implode('`,`', $flds_names).'`';
    print "\n\n<!-- $flds -->";

    //get table prefix
    $id_field = $flds_names[0];
    $prefix = substr($id_field,0,3);
    $where = '';

    if($id>0){
        if($prefix=='rst'){
            $where = ' where rst_RecTypeID='.intval($id);
        }else{
            $where = " where $id_field=".intval($id);
        }
    }


    $query = "select $flds from $tname".$where;
    $res = $mysqli->query($query);

    while ($row = $res->fetch_assoc()) {

        if($prefix=='rty' && !(@$row[$id_field]>0)) {continue;}

        print "<$prefix>";
        foreach($flds_list as $fld => $type){

            $val = $row[$fld];
            if(strpos($type,'text')!==false || strpos($type,'varchar')!==false){
                $val = htmlspecialchars($mysqli->real_escape_string($val));
            }elseif(strpos($fld,'OriginatingDBID')!==false){
                if(!($val>0)){
                    $val = HEURIST_DBID;
                }
            }elseif(strpos($fld,'IDInOriginatingDB')!==false){
                if(HEURIST_DBID>0 && !($val>0)){
                    $val = $val[$id_field];
                }
            }
            print "<$fld>$val</$fld>";
        }
        print "</$prefix>\n";

    }
    print "</$tname_tag>";

    $res->close();
}
?>
