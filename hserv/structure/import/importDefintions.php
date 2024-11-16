<?php
/**
* importDefinitions.php - add complete set of definitions to database
* see dbUtils::databaseCreateFull - it creates database and import defintions
* from file coreDefinitions.txt created with getDBStructureAsSQL.
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

class ImportDefinitions {

    private $mysqli = null;
    private $system = null;
    private $initialized = false;

    public function initialize($mysqli=null)
    {
        if ($this->initialized) {return;}

        global $system;
        $this->system = $system;

        if($mysqli){
            $this->mysqli = $mysqli;
        }else{
            $this->mysqli = $system->getMysqli();
        }

        define('START_TOKEN', '>>StartData>>');
        define('END_TOKEN', '>>EndData>>');

        $this->initialized = true;
    }


    //
    //
    //
    private function validateDefintions( $data ){

        if(!$data){
            return 'Definition data is empty';
        }

        $start_tag_count = substr_count ( $data , START_TOKEN );
        $end_tag_count = substr_count ( $data , END_TOKEN );
        //check the number of start and end quotes
        if($start_tag_count!=$end_tag_count){
            return "Error: Definition data is invalid: The number of open and close tags must be equal";
        }
        if($start_tag_count<15){
            return "Error: Definition data is invalid: It seems it is truncated. Only "
            .$start_tag_count." definitions found. At least 15 expected";
        }

        //verify that start is always before end
        $offset = 0;
        while (true) {
            $pos1 = strpos(START_TOKEN, $data, $offset);
            $pos2 = strpos(END_TOKEN, $data, $offset+10);
            if($pos1===false){
                break;
            }
            if($pos2>$pos1){
                return  "Definition data is invalid: Missing open tag";
            }
            $offset = $pos2;
        }
        return false;

    }

    //
    //
    //
    private function prepareDataSet($splittedData) { // returns and removes the first set of data between markers from $splitteddata

            $splittedData2 = explode(END_TOKEN, $splittedData);
            $i = 1;
            $size = strlen($splittedData2[0]);
            $testData = $splittedData2[0];
            if(!($testData[$size - $i] == ")")) {
                while((($size - $i) > 0) && (($testData[$size - $i]) != ")")) {
                    if($i == 10) {
                        $i = -1;
                        break;
                    }
                    $i++;
                }
            }
            if($i != -1) {
                $i--;
                $splittedData3 = substr($splittedData2[0],0,-$i);
            }

            return $splittedData3;

    } // getNextDataSet


    //
    //
    //
    public function doImport( $definitions_filename ) {

        $file = fopen($definitions_filename, "r");
        $output = "";
        while(!feof($file)) {
            $output = $output . fgets($file, 4096);
        }
        fclose($file);
        $data = $output;

        $error = $this->validateDefintions( $data );
        if($error){
            //add error
            $this->system->addError(HEURIST_SYSTEM_CONFIG,  $error);
            return false;
        }

        $this->mysqli->query("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
        mysql__foreign_check( $this->mysqli, false );

        //order of tables - this must correspond with order in getDBStructureAsSQL.php
        $tables = array('',
        'defRecTypeGroups',
        'defDetailTypeGroups',
        'defVocabularyGroups',
        'defOntologies',
        'defTerms',
        'defTermsLinks',
        'defRecTypes',
        'defDetailTypes',
        'defRecStructure',
        'defRelationshipConstraints',
        'defFileExtToMimetype',
        'defTranslations',
        'usrSavedSearches',
        'sysDashboard' // added 12/11/18
        );

        $splittedData = explode(START_TOKEN, $data);

        foreach($splittedData as $idx=>$tableData){

            if($idx>=count($tables)){
                break;
            }elseif($tables[$idx]==''){
                continue;
            }

            $dataSet = $this->prepareDataSet($tableData);


            if(($dataSet == "") || (strlen($dataSet) <= 2)) { // no action if no data
                continue;
            }

                $flds = mysql__select_list2($this->mysqli, 'SHOW COLUMNS FROM '.$tables[$idx]);
                if($tables[$idx]=='defTermsLinks'){
                    array_shift($flds);//remove primary key field
                }

                $flds = '`'.implode('`,`', $flds).'`';


                $query = 'INSERT INTO `'.$tables[$idx]."` ($flds) VALUES ". $dataSet;
                $this->mysqli->query($query);
                if($this->mysqli->error && $this->mysqli->error!='') {

                    $merror = $this->mysqli->error;
                    $error = 'Error inserting data into '.$tables[$idx];

                    $this->mysqli->query("SET SESSION sql_mode=''");
                    mysql__foreign_check( $this->mysqli, true );

                    //add error
                    $this->system->addError(HEURIST_DB_ERROR,  $error, $merror);
                    return false;
                }
        }//for

        $this->mysqli->query("SET SESSION sql_mode=''");
        mysql__foreign_check( $this->mysqli, true );


        return true;
    }

}
?>
