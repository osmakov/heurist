<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* exportRecordsGEPHI.php - class to export records as GEPHI XML
*
* Controller is records_output
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

namespace hserv\records\export;
use hserv\records\export\ExportRecords;

/**
*
*  setSession - switch current datbase
*  output - main method
*
*/
class ExportRecordsGEPHI extends ExportRecords {

    //to store gephi links
    private $gephi_links_dest = null;
    private $fd_links = null;
    private $links_cnt = 0;

    private $relmarker_fields = [];

//
//
//
protected function _outputPrepareFields($params){

    $this->retrieve_detail_fields = !empty($params['columns']) ? prepareIds($params['columns']) : false;
    $this->retrieve_header_fields = 'rec_ID,rec_RecTypeID,rec_Title';

}

//
//
//
protected function _outputHeader(){

    $this->gephi_links_dest = tempnam($this->system->getSysDir(DIR_SCRATCH), "links");
    $this->fd_links = fopen($this->gephi_links_dest, 'w');//less than 1MB in memory otherwise as temp file
    if (false === $this->fd_links) {
        $this->system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
        return false;
    }

    $t2 = new \DateTime();
    $dt = $t2->format('Y-m-d');

    //although anyURI is defined it is not recognized by gephi v0.92
    $heurist_url = HEURIST_BASE_URL.'?db='.$this->system->dbname();

    $rec_fields = '';
    if(!empty($this->retrieve_detail_fields)){

        $id_idx = 5;

        foreach ($this->retrieve_detail_fields as $dty_ID) {

            $dty_Name = mysql__select_value($this->mysqli, "SELECT dty_Name FROM defDetailTypes WHERE dty_ID = {$dty_ID}");
            $rec_fields .= "\n\t\t\t\t<attribute id=\"{$id_idx}\" title=\"{$dty_Name}\" type=\"string\"/>";

            $id_idx ++;
        }
    }

    // Relationship record values
    $rel_RecTypeID = $this->system->getConstant('RT_RELATION');
    $rel_Source = $this->system->getConstant('DT_PRIMARY_RESOURCE');
    $rel_Target = $this->system->getConstant('DT_TARGET_RESOURCE');
    $rel_Type = $this->system->getConstant('DT_RELATION_TYPE');
    $rel_Start = $this->system->getConstant('DT_START_DATE');
    $rel_End = $this->system->getConstant('DT_END_DATE');

    $rel_fields = '';
    if($rel_RecTypeID && $rel_Source && $rel_Target && $rel_Type && $rel_Start && $rel_End){

        $query = "SELECT rst_DisplayName, rst_DetailTypeID FROM defRecStructure WHERE rst_RecTypeID = ? AND rst_DetailTypeID NOT IN (?,?,?,?,?)";
        $query_params = ['iiiiii', $rel_RecTypeID, $rel_Source, $rel_Target, $rel_Type, $rel_Start, $rel_End];
        $res = mysql__select_param_query($this->mysqli, $query, $query_params);

        $id_idx = 6;

        if($res){

            while($row = $res->fetch_row()){

                $rel_fields .= "\n\t\t\t\t<attribute id=\"{$id_idx}\" title=\"{$row[0]}\" type=\"string\"/>";
                $this->relmarker_fields[] = $row[1];

                $id_idx ++;
            }
        }
    }

    $gephi_header = <<<XML
        <gexf xmlns="http://www.gexf.net/1.2draft" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance"
            xsi:schemaLocation="http://www.gexf.net/1.2draft http://www.gexf.net/1.2draft/gexf.xsd" version="1.2">
            <meta lastmodifieddate="{$dt}">
                <creator>HeuristNetwork.org</creator>
                <description>Visualisation export $heurist_url </description>
            </meta>
            <graph mode="static" defaultedgetype="directed">
                <attributes class="node">
                    <attribute id="0" title="name" type="string"/>
                    <attribute id="1" title="image" type="string"/>
                    <attribute id="2" title="rectype" type="string"/>
                    <attribute id="3" title="count" type="float"/>
                    <attribute id="4" title="url" type="string"/>{$rec_fields}
                </attributes>
                <attributes class="edge">
                    <attribute id="0" title="relation-id" type="float"/>
                    <attribute id="1" title="relation-name" type="string"/>
                    <attribute id="2" title="relation-image" type="string"/>
                    <attribute id="3" title="relation-count" type="float"/>
                    <attribute id="4" title="relation-start" type="string"/>
                    <attribute id="5" title="relation-end" type="string"/>{$rel_fields}
                </attributes>
                <nodes>
XML;

    $gephi_header = XML_HEADER.$gephi_header;

    fwrite($this->fd, $gephi_header);

    $this->links_cnt = 0;
}

//
//
//
protected function _outputRecord($record){

    $recID = intval($record['rec_ID']);
    $rty_ID = intval($record['rec_RecTypeID']);
    $name   = htmlspecialchars($record['rec_Title']);
    $image  = htmlspecialchars(HEURIST_BASE_URL.'?db='.$this->system->dbname().'&icon='.$rty_ID);
    $recURL = htmlspecialchars(HEURIST_BASE_URL.'recID='.$recID.'&fmt=html&db='.$this->system->dbname());

    $rec_values = '';
    if(is_array($this->retrieve_detail_fields)){
        $this->retrieve_detail_fields = array();
    }

    $att_id = 4;
    foreach($this->retrieve_detail_fields as $dty_ID){

        $att_id ++;
        $values = array_key_exists($dty_ID, $record['details']) && is_array($record['details'][$dty_ID]) ?
                    $record['details'][$dty_ID] : null;

        if(empty($values)){
            continue;
        }

        $this->_processFieldData($dty_ID, $values);

        if(empty($values)){
            continue;
        }

        $rec_values .= "\n\t\t\t<attvalue for=\"{$att_id}\" value=\"{$values}\"/>";
    }

            $gephi_node = <<<XML
<node id="{$recID}" label="{$name}">
    <attvalues>
        <attvalue for="0" value="{$name}"/>
        <attvalue for="1" value="{$image}"/>
        <attvalue for="2" value="{$rty_ID}"/>
        <attvalue for="3" value="0"/>
        <attvalue for="4" value="{$recURL}"/>
        {$rec_values}
    </attvalues>
</node>
XML;

    fwrite($this->fd, $gephi_node);

    $links = recordSearchRelated($this->system, $recID, 0, false);
    if($links['status']!=HEURIST_OK){
        return false;
    }

    if(@$links['data']['direct']){
        fwrite($this->fd_links, $this->_composeGephiLinks($this->records, $links['data']['direct'], $this->links_cnt, 'direct'));
    }
    if(@$links['data']['reverse']){
        fwrite($this->fd_links, $this->_composeGephiLinks($this->records, $links['data']['reverse'], $this->links_cnt, 'reverse'));
    }

    return true;

}

//
//
//
protected function _outputFooter(){

        fwrite($this->fd, '</nodes>');

        //include links
        fwrite($this->fd, '<edges>'.file_get_contents($this->gephi_links_dest).'</edges>');

        fwrite($this->fd, '</graph></gexf>');

        fclose($this->fd_links);

}


/**
* returns xml string with gephi links
*
* @param mixed $this->records - array of record ids to limit output only for links in this array
* @param mixed $links - array of relations produced by recordSearchRelated
*/
private function _composeGephiLinks(&$records, &$links, &$links_cnt, $direction){

    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes($this->system, null, 2);
    }
    if(self::$defTerms==null) {
        self::$defTerms = dbs_GetTerms($this->system);
        self::$defTerms = new \DbsTerms($this->system, self::$defTerms);
    }

    $idx_dname = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];


    $edges = '';

    if($links){

        foreach ($links as $link){
            if($direction=='direct'){
                $source =  $link->recID;
                $target =  $link->targetID;
            }else{
                $source = $link->sourceID;
                $target = $link->recID;
            }

            $dtID = $link->dtID;
            $trmID = $link->trmID;
            $relationName = "Floating relationship";
            $relationID = 0;

            $startDate = empty(@$link->dtl_StartDate) ? '' : $link->dtl_StartDate;
            $endDate = empty(@$link->dtl_EndDate) ? '' : $link->dtl_EndDate;

            if(in_array($source, $records) && in_array($target, $records)){

                if($dtID > 0) {
                    $relationName = self::$defDetailtypes['typedefs'][$dtID]['commonFields'][$idx_dname];
                    $relationID = $dtID;
                }elseif($trmID > 0) {
                    $relationName = self::$defTerms->getTermLabel($trmID, true);
                    $relationID = $trmID;
                }

                $relationName  = htmlspecialchars($relationName);
                $links_cnt++;

                $rel_values = '';
                $att_id = 5;
                if(!empty($this->relmarker_fields) && !empty($link->relationID) && intval($link->relationID) > 0){

                    $record = recordSearchByID($this->system, intval($link->relationID), $this->relmarker_fields, 'rec_ID');

                    foreach($this->relmarker_fields as $dty_ID){

                        $att_id ++;

                        if(!array_key_exists($dty_ID, $record['details']) || empty($record['details'][$dty_ID])){
                            continue;
                        }

                        $values = $record['details'][$dty_ID];
                        $this->_processFieldData($dty_ID, $values);

                        if(empty($values)){
                            continue;
                        }

                        $rel_values .= "\n\t\t<attvalue for=\"{$att_id}\" value=\"{$values}\"/>";
                    }
                }

                $edges = $edges.<<<XML
<edge id="{$links_cnt}" source="{$source}" target="{$target}" weight="1">
    <attvalues>
        <attvalue for="0" value="{$relationID}"/>
        <attvalue for="1" value="{$relationName}"/>
        <attvalue for="3" value="1"/>
        <attvalue for="4" value="{$startDate}"/>
        <attvalue for="5" value="{$endDate}"/>{$rel_values}
    </attvalues>
</edge>
XML;


            }
        }//for
    }
    return $edges;
}

private function _processFieldData($dty_ID, &$values){

    $dty_Type = mysql__select_value($this->mysqli, "SELECT dty_Type FROM defDetailTypes WHERE dty_ID = ?", ['i', $dty_ID]);

    foreach($values as $dtl_ID => $value){

        switch ($dty_Type) {
            case 'file': // get external URL / Heurist URL

                $f_id = $value['file']['ulf_ObfuscatedFileID'];
                $external_url = $value['file']['ulf_ExternalFileReference'];

                $value = empty($external_url) ? HEURIST_BASE_URL_PRO."?db=".$this->system->dbname()."&file={$f_id}" : $external_url;
                break;

            case 'enum': // get term label

                if(is_numeric($value)){
                    $value = intval($value);
                    $value = mysql__select_value($this->mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = ?", ['i', $value]);
                }

                break;

            case 'resource': // get record title

                if(is_numeric($value)){
                    $value = intval($value);
                    $value = mysql__select_value($this->mysqli, "SELECT rec_Title FROM Records WHERE rec_ID = ?", ['i', $value]);
                }elseif(is_array($value)){
                    $value = $value["title"];
                }

                break;

            default:
                break;
        }

        $values[$dtl_ID] = htmlspecialchars($value);
    }

    $values = is_array($values) ? implode('|', $values) : $values;
}

} //end class
?>