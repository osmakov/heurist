<?php
namespace hserv\entity;
use hserv\utilities\USanitize;
use hserv\entity\DbEntitySearch;

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* Base class for all database entities.
*
* This abstract class handles core functionalities such as reading configurations, handling
* field data, and providing save, delete, and search operations for database entities.
* Base class for all db entities
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/
abstract class DbEntityBase
{
    /** @var mixed $system System handler for operations */
    protected $system;

    /** @var bool $need_transaction Set to true if the action requires a transaction */
    protected $need_transaction = true;

    /** @var bool $is_addition Flag to reset all primary fields to zero to force addition (POST request) */
    protected $is_addition = false;

    /**
     * @var array|null $data Field values used for search and update.
     * Contains `data[fields]` for particular records, initiated in prepareRecords().
     * usually this is $_REQUEST
     */
    protected $data = null;

    /** @var array|null $config Configuration loaded from a JSON file */
    protected $config;

    /** @var string $primaryField Name of the primary key field from $config marked with dty_Role="primary" */
    protected $primaryField;

    /** @var array $multilangFields Names of multi-language fields from $config with rst_MultiLang=1 */
    protected $multilangFields = array();

    /** @var array $fields Fields structure description from JSON, used for validation and access */
    protected $fields;

    /** @var array $fieldsNames Non-virtual field names */
    protected $fieldsNames;

    /**
     * @var array $records Holds several records for delete/update actions.
     * Extracted from $data in prepareRecords().
     */
    protected $records = array();

    /**
     * @var array $translation Translated values for fields extracted from the request in prepareRecords().
     */
    protected $translation = array();

    /**
     * @var array $recordIDs IDs of records to update/delete, extracted from $data in prepareRecords().
     * Necessary for permission validation.
     */
    protected $recordIDs = array();

    /** @var string $entityName The name of the table or entity */
    private $entityName;

    /** @var array|null $foreignChecks Array of queries to validate references before deletion */
    protected $foreignChecks = null;

    /** @var bool $isDeleteReady Flag indicating if the entity is ready for deletion */
    protected $isDeleteReady = false;

    /** @var bool $requireAdminRights Set to true if admin rights are required for the operation */
    protected $requireAdminRights = true;

    /** @var array|null $duplicationCheck Check for duplication */
    protected $duplicationCheck = null;

     /**
     * Constructor - Loads configuration from JSON file.
     *
     * @param mixed $system The system instance.
     * @param array|null $data The data to be initialized.
     */
    public function __construct( $system, $data=null ) {
       $this->system = $system;

       if(method_exists($this,'init')){
            $this->init();
       }

       $reflect = new \ReflectionClass($this);

       $this->entityName = lcfirst(substr($reflect->getShortName(),2));
       if($data){
           $this->setData($data);
       }else{
           $this->config = array('entityName'=>$this->entityName, 'entityTable'=>$this->entityName);
       }
    }


    /**
     * Verify if the entity is valid (configuration loaded and fields not empty).
     *
     * @return bool True if valid, otherwise false.
     */
    public function isvalid(){
        return is_array($this->config) && !isEmptyArray($this->fields);
    }

    /**
     * Read configuration from the JSON file.
     *
     * @return void
     */
    private function _readConfig(){

        if(@$this->data['entity']){
            $this->entityName = lcfirst(@$this->data['entity']);
        }

        $entity_file = dirname(__FILE__).'/'.basename($this->entityName.'.json'); //HEURIST_DIR.'hserv/entity

        if(file_exists($entity_file)){

           $json = file_get_contents($entity_file);

           $this->config = json_decode($json, true);


           if(is_array($this->config) && $this->config['fields']){

                $this->fields = array();
                $this->_readFields($this->config['fields']);
           }

           if(!$this->isvalid()){
                $this->system->addError(HEURIST_SYSTEM_FATAL,
                    "Configuration file $entity_file is invalid. Cannot init instance on server");
           }

        }else{
           $this->system->addError(HEURIST_SYSTEM_FATAL, 'Cannot find configuration for entity '
                        .$this->entityName.' in '.dirname(__FILE__));
        }
    }

    /**
     * Initialize the entity (abstract method to be implemented in subclasses).
     *
     * @return void
     */
    public function init(){}

    /**
     * Set data for the entity from the client request.
     *
     * @param array $data Data to be set.
     * @return void
     */
    public function setData($data){
        $this->data = $data;
        $this->records = null;

        if(!$this->isvalid()){
           $this->_readConfig();
           //rename generic ID or recID to valid primary field name for particular entity
           if(@$this->data[$this->primaryField]==null){
                if(@$this->data['ID']>0) {
                    $this->data[$this->primaryField] = $this->data['ID'];
                }elseif(@$this->data['recID']>0) {
                    $this->data[$this->primaryField] = $this->data['recID'];
                }
           }
        }
    }

    /**
     * Get the current data of the entity.
     *
     * @return array|null Returns the current data.
     */
    public function getData(){
        return $this->data;
    }

    /**
     * Set records for deletion or update actions.
     *
     * @param array $records Records to be set.
     * @return void
     */
    public function setRecords($records){
        $this->records = $records;
    }


    /**
     * Get the current records.
     *
     * @return array Returns the current records.
     */
    public function records(){
        return $this->records;
    }


     /**
     * Set whether a transaction is required.
     *
     * @param bool $value True if transaction is required, false otherwise.
     * @return void
     */
    public function setNeedTransaction($value){
        $this->need_transaction = $value;
    }

     /**
     * Get the configuration.
     *
     * @param string $locale Locale for configuration.
     * @return array|null Configuration data.
     */
    public function config( $locale='en' ){

        if(!@$this->config['locale']){
            @$this->config['locale'] = 'en';
        }

        if(@$this->config['locale']!=$locale){
            $this->_readConfigLocale($locale);
        }

        return $this->config;
    }

    //
    // working with config/setting  see configEntity.js
    //
    public function files( $action ){

        if(!($this->system->get_user_id()>0)){
            $this->system->addError(HEURIST_REQUEST_DENIED, 'Insufficient rights (logout/in to refresh) for this operation');
            return false;
        }

        $res = false;

        $folder = $action['folder'];//folder
        $operation = $action['operation'];
        $content = @$action['content'];
        $filename = @$action['file'];
        $entity_name = $this->config['entityName'];
        $rec_ID = @$action['rec_ID'];

        //available values are hardcoded - prevent
        if($entity_name!='defRecTypes'){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Entity parameter is wrong or not defined');
            $res = false;
        }elseif($folder==null || !in_array($folder, array('datatable','csvexport','crosstabs'))){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Folder parameter is wrong or not defined');
            $res = false;
        }else{

        $path = HEURIST_FILESTORE_DIR.DIR_ENTITY.$entity_name.'/'.$folder.'/'.($rec_ID>0?$rec_ID.'/':'');

        if($operation=='list'){

            if($rec_ID=='all'){

                if(file_exists($path)){

                    $dirs = array();
                    $dir = new \DirectoryIterator($path);
                    foreach ($dir as $node) {
                        if ($node->isDir() && !$node->isDot()) {
                            $folder_name = $node->getFilename();
                            if(is_numeric($folder_name)){
                                array_push($dirs, $path.$folder_name.'/');
                            }
                        }
                    }
                    $res = folderContent($dirs, 'cfg');
                }else{
                    $res = array('count'=>0,'reccount'=>0,'order'=>array());
                }
            }else{
                $res = folderContent($path, 'cfg');
            }

        }elseif($operation=='get'){

            $sMsg = 'Cannot get content of settings file. ';

            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.error_WrongParam('Filename'));
                $res = false;
            }elseif (!file_exists($path.$filename)){
                $this->system->addError(HEURIST_ERROR, $sMsg.'File does not exist');
                $res = false;
            }else{
                $res = file_get_contents($path.$filename);
            }

        }elseif($operation=='put'){

            $filename = USanitize::sanitizeFileName($filename);

            //verify exetension for $filename
            $path_parts = pathinfo($filename);
            $ext = strtolower(@$path_parts['extension']);

            $sMsg = 'Cannot save content the settings file. ';

            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.error_WrongParam('Filename'));
                $res = false;
            }elseif($ext!='cfg'){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Only cfg extension allowed for configuration file');
                $res = false;
            }elseif($content==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.error_WrongParam('Content'));
                $res = false;
            }else{

                //verify that content is valid JSON
                $config = json_decode($content, true);
                if(!is_array($config)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Content is not valid json');
                    $res = false;
                }else{

                    $swarn = folderCreate2($path, '', false);
                    if($swarn!=''){
                        $this->system->addError(HEURIST_ERROR, $sMsg.$swarn);
                        $res = false;
                    }else{
                        $res = file_put_contents($path.$filename, $content);
                        if($res!==false){
                            $res = $filename;
                        }
                    }
                }
            }

        }elseif($operation=='rename'){

            $sMsg = 'Cannot rename the settings file. ';

            $fileOld = @$action['fileOld'];
            if(file_exists($path.$fileOld)){

                $filename = USanitize::sanitizeFileName($filename);

                //verify exetension for $filename
                $path_parts = pathinfo($filename);
                $ext = strtolower(@$path_parts['extension']);


                if($filename==null){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.error_WrongParam('New filename'));
                    $res = false;
                }elseif($ext!='cfg'){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Only cfg extension allowed for configuration file');
                    $res = false;
                }elseif(!copy($path.$fileOld, $path.$filename)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg);
                    $res = false;
                }else{
                    unlink($path.$fileOld);
                    $res = $filename;
                }

            }else{
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.'Settings file does not exist');
                $res = false;
            }

        }elseif($operation=='delete'){

            $sMsg = 'Cannot remove the settings file. ';

            if($filename==null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $sMsg.error_WrongParam('Filename'));
                $res = false;
            }elseif (!file_exists($path.$filename)){
                $this->system->addError(HEURIST_ERROR, $sMsg.'File does not exist');
                $res = false;
            }else{
                $res = unlink($path.$filename);
            }

        }

        }

        return $res;
    }


    /**
     * Perform actions based on the current data request.
     *
     * @return mixed Result of the action.
     */
    public function run(){

        if(!$this->isvalid()){
            return false;
        }

        $res = false;

        $action =@$this->data['a'];

        switch ($action) {
           case 'search':
                $res = $this->search();
                break;
           case 'title':
                $res = $this->search_title();
                break;
           case 'add':
                $this->is_addition = true;
                $this->data['a'] = 'save';
                $res = $this->save();
                $this->is_addition = false;
                break;
           case 'save':
                $res = $this->save();
                break;
           case 'delete':
                $res = $this->delete();
                break;
           case 'config':
                $res = $this->config( @$this->data['locale'] );
                break;
           case 'files':
                // working with settings/config files
                // get list of files by extension
                // put data into file
                // load date from file
                $res = $this->files($this->data);
                break;
           case 'counts':
                //various counts(aggregations) request - implementation depends on entity
                $res = $this->counts();
                break;
           case 'action':
           case 'batch':
                $res = $this->batch_action();
                if($res &&
                    !(@$this->data['get_translations'] ||
                    in_array($this->config['entityName'],array('defRecTypes','defDetailTypes','defTerms','defRecTypes'))))
                {
                        $this->_cleanDbDefCache();
                }
                break;
           default:
                $this->system->addError(HEURIST_INVALID_REQUEST, "Type of request not defined or not allowed");
        }
        return $res;
    }

    //
    //
    //
    private function _cleanDbDefCache(){

        if(is_array($this->config) &&
            in_array($this->config['tablePrefix'], array('rty','dty','rst','trm','rtg','dtg','vcg','swf')))
        { //affected entity
            $this->system->cleanDefCache();
        }
    }

    /**
     * Save the records to the database.
     *
     * @return array|false An array of saved record IDs or false on failure.
     */
    public function save(){

        //extract records from $_REQUEST data
        if($this->records==null && !$this->prepareRecords()){ //records can be pepared beforehand
            return false;
        }

        //validate permission for current user and set of records see $this->recordIDs
        if(!$this->_validatePermission()){
            return false;
        }

        //validate values and check mandatory fields
        foreach($this->records as $record){

            $this->data['fields'] = $record;

            //validate mandatory fields
            if(!$this->_validateMandatory()){
                return false;
            }

            //validate values
            if(!$this->_validateValues()){
                return false;
            }
        }

        //array of inserted or updated record IDs
        $results = array();

        //start transaction
        $mysqli = $this->system->get_mysqli();

        if($this->need_transaction){
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }

        foreach($this->records as $rec_idx => $record){

            //exclude virtual fields
            $fieldvalues = $record;
            $values = array();
            foreach($this->fields as $fieldname=>$field_config){
                if(@$field_config['dty_Role']=='virtual' || !array_key_exists($fieldname, $record)) {continue;}
                $values[$fieldname] = $record[$fieldname];
            }

            $isinsert = (intval(@$record[$this->primaryField])<1);

            if(!$isinsert && count($values)<2){
                //no fields except id - skip this record
                $ret = $record[$this->primaryField];
            }else{
                //save data
                $ret = mysql__insertupdate($mysqli,
                                        $this->config['tableName'], $this->fields,
                                        $values );
            }

            if($ret===true || $ret==null){ //it returns true for non-numeric primary field
                   $results[] = $record[$this->primaryField];
            }elseif(is_numeric($ret)){
                   $this->records[$rec_idx][$this->primaryField] = $ret;
                   $results[] = $ret;
            }else{
                    //rollback
                    if($this->need_transaction){
                        $mysqli->rollback();
                        if($keep_autocommit===true) {$mysqli->autocommit(true);}
                    }
                    $this->system->addError(HEURIST_INVALID_REQUEST,
                        'Cannot save data in table '.$this->config['entityName'], $ret);
                    return false;
            }

            //update translations
            if(!isEmptyArray(@$this->translation[$rec_idx]))
            {
                foreach($this->multilangFields as $fieldname){
                    //delete previous translations for this record
                    if(!$isinsert){
                        $mysqli->query('DELETE FROM defTranslations where trn_Source="'
                            .$fieldname.'" AND trn_Code='.$this->records[$rec_idx][$this->primaryField]);
                    }
                    if(@$this->translation[$rec_idx][$fieldname]!=null){

                        $langs = $this->translation[$rec_idx][$fieldname];

                        foreach($langs as $lang=>$value){
                            if($value!=null && trim($value)!=''){
                                mysql__insertupdate($mysqli,
                                        'defTranslations', 'trn',
                                array('trn_ID'=>0,
                                      'trn_Source'=>$fieldname,
                                      'trn_Code'=>$this->records[$rec_idx][$this->primaryField],
                                      'trn_LanguageCode'=>$lang,
                                      'trn_Translation'=>$value));
                            }
                        }
                    }
                }

            }elseif(!$isinsert && !empty($this->multilangFields)){
                //remove all translation for this record

                $mysqli->query('DELETE FROM defTranslations where trn_Source LIKE "'
                .$this->config['tablePrefix']
                .'%" AND trn_Code='.$this->records[$rec_idx][$this->primaryField]);

            }



        }//for records
        if($this->need_transaction){
            //commit
            $mysqli->commit();
            if($keep_autocommit===true) {$mysqli->autocommit(true);}

            $this->_cleanDbDefCache();
        }
        return $results;
    }//save

    /**
     * Prepare records for deletion by checking their IDs and permissions.
     *
     * @return bool True if the records are ready for deletion, false otherwise.
     */
     protected function deletePrepare(){

        if(!@$this->recordIDs){
            $this->recordIDs = prepareIds($this->data[$this->primaryField]);
        }

        if(empty($this->recordIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }

        if(!$this->_validatePermission()){
            return false;
        }

        $this->isDeleteReady = true;

        if(empty($this->foreignChecks)){
            $this->isDeleteReady = true;
            return true;
        }

        $compare = (count($this->recordIDs)==1?'='.$this->recordIDs[0] :SQL_IN.implode(',', $this->recordIDs).')');

        foreach($this->foreignChecks as $check){

            $query = $check[0];

            if(strpos($query,'#IDS#')>0){
                $query = str_replace('#IDS#',implode(',', $this->recordIDs),$query);
            }else{
                $query .= $compare;
            }

            $ret = mysql__select_value($this->system->get_mysqli(), $query);

            if($ret>0){
                $msg = @$check[1]?$check[1]:'Cannot delete '.$this->config['entityTitle'];
                $this->system->addError(HEURIST_ACTION_BLOCKED, $msg);
                return false;
            }
        }

        $this->isDeleteReady = true;
        return true;
    }

    /**
     * Delete records from the database.
     *
     * @param bool $disable_foreign_checks Disable foreign key checks.
     * @return bool True on successful deletion, false otherwise.
     */
    public function delete($disable_foreign_checks=false){

        if(!$this->isDeleteReady && !$this->deletePrepare()){
            return false;
        }

        $mysqli = $this->system->get_mysqli();

        mysql__foreign_check($mysqli, false);
        $query = SQL_DELETE.$this->config['tableName'].SQL_WHERE.predicateId($this->primaryField, $this->recordIDs);

        $ret = $mysqli->query($query);
        $affected = $mysqli->affected_rows;

        //
        // delete from translation table all fields that starts with current table prefix and with given record ids
        // array('rty','dty','ont','vcb','trm','rst','rtg')
        //
        if(!empty($this->multilangFields))
        {
            $mysqli->query(SQL_DELETE.'defTranslations where trn_Source LIKE "'
                                .$this->config['tablePrefix'].'%" AND '
                                .predicateId('trn_Code', $this->recordIDs));

        }

        mysql__foreign_check($mysqli, true);

        if(!$ret){
            $this->system->addError(HEURIST_DB_ERROR,
                    'Cannot delete from table '.$this->config['entityName'], $mysqli->error);
            return false;
        }elseif($affected===0){
            $this->system->addError(HEURIST_NOT_FOUND, 'Cannot delete. No entries found');
            return false;
        }

        $this->_cleanDbDefCache();

        return true;
    }

    /**
     * Batch action handler (to be implemented in subclasses).
     *
     * @return mixed Result of the batch action.
     */
    public function batch_action(){
        return false;
    }

    //
    // various counts(aggregations) request - implementation depends on entity
    //
    public function counts(){
        return 0;
    }

    //
    //
    //
    protected function _validateFieldsForSearch(){

            foreach($this->data['details'] as $fieldname){
                if(!@$this->fields[$fieldname]){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                    return false;
                }
            }
            //ID field is mandatory and MUST be first in the list
            $idx = array_search($this->primaryField, $this->data['details']);
            if($idx>0){
                unset($this->data['details'][$idx]);
                $idx = false;
            }
            if($idx===false){
                array_unshift($this->data['details'], $this->primaryField);
            }

            return true;
    }


    //
    // Validates permission for delete and update operations
    //
    protected function _validatePermission(){


        if($this->requireAdminRights &&
            !$this->system->is_admin() &&
            ((!isEmptyArray($this->recordIDs))
            || (!isEmptyArray($this->records)))){ //there are records to update/delete

            $ent_name = @$this->config['entityTitlePlural']?$this->config['entityTitlePlural']:'this entity';

            $this->system->addError(HEURIST_REQUEST_DENIED,
                    'You are not admin and can\'t edit '.$ent_name
                    .'. Insufficient rights (logout/in to refresh) for this operation '
                    .$this->system->get_user_id().'  '.print_r($this->system->getCurrentUser(), true));
            // You have to be Administrator of group \'Database Managers\' for this operation
            return false;
        }

        if(!$this->system->has_access()){
             $this->system->addError(HEURIST_REQUEST_DENIED,
                    'You must be logged in. Insufficient rights (logout/in to refresh) for this operation');
             return false;
        }

        return true;
    }

    //
    //
    //
    protected function _validateValues(){

        $fieldvalues = $this->data['fields'];//current record

        foreach($this->fields as $fieldname=>$field_config){
            if(@$field_config['dty_Role']=='virtual') {continue;}

            $value = @$fieldvalues[$fieldname];

            //ulf_MimeExt is the only nonnumeric resource
            if(@$field_config['dty_Type']=='resource' && $fieldname!='ulf_MimeExt'){
                if(intval($value)<1) {$this->data['fields'][$fieldname] = null;}
            }
        }

        return true;
    }

    //
    //
    //
    protected function _validateMandatory(){

        $fieldvalues = $this->data['fields'];

        $rec_ID = intval(@$fieldvalues[$this->primaryField]);
        $isinsert = ($rec_ID<1);

        foreach($this->fields as $fieldname=>$field_config){
            if (@$field_config['dty_Role']=='virtual' ||
                @$field_config['dty_Role']=='primary' ||
                @$field_config['rst_RequirementType'] != 'required')
            {
                continue;
            }

            if(!(array_key_exists($fieldname, $fieldvalues) || $isinsert)){
                continue;
            }

            $value = @$fieldvalues[$fieldname];

            if(@$field_config['rst_MultiLang'] && is_array($value)){
                $value = !empty($value)?$value[0]:'';
            }

            if( isEmptyStr($value) ){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Field $fieldname is mandatory.");
                return false;
            }
        }
        return true;
    }

    //
    // Returns localized configuration
    //
    private function _readConfigLocale( $locale='en' ){

        $entity_file = dirname(__FILE__).'/'.lcfirst(@$this->data['entity']) //HEURIST_DIR.'hserv/entity/'
            .($locale=='en'?'':('_'.$locale)).'.json';

        if(file_exists($entity_file)){

           $json = file_get_contents($entity_file);
           $locale_config = json_decode($json, true);

           $this->config['locale'] = $locale;
           $this->config['entityTitle'] = $locale_config['entityTitle'];
           $this->config['entityTitlePlural'] = $locale_config['entityTitlePlural'];

           $this->_fieldsSetLocale( $this->config['fields'], $locale_config['fields'] );
        }
    }

    //
    //
    //
    private function _getFieldByID($id, $fields){
        foreach($fields as $field){

            if(@$field['dtID']==$id){
                return $field;
            }elseif(!isEmptyArray(@$field['children'])){
                $res = $this->_getFieldByID($id, $field['children']);
                if($res){
                    return $res;
                }
            }
        }
        return null;
    }

    //
    // assign localized name and description for fields
    //
    private function _fieldsSetLocale( &$fields, $fields_locale ){

        foreach($fields as $idx=>$field){

            if(!isEmptyArray(@$field['children'])){

                $fld_loc = $this->_getFieldByID($field['dtID'], $fields_locale);
                if($fld_loc && @$fld_loc['groupHeader']){
                    $fields[$idx]['groupHeader'] = $fld_loc['groupHeader'];
                }

                $this->_fieldsSetLocale($fields[$idx]['children'], $fields_locale);

            }else{

                $fld_loc = $this->_getFieldByID($field['dtID'], $fields_locale);
                if($fld_loc && @$fld_loc['dtFields']){
                    if(@$fld_loc['dtFields']['rst_DisplayName']){
                        $fields[$idx]['dtFields']['rst_DisplayName'] = $fld_loc['dtFields']['rst_DisplayName'];
                    }
                    if(@$fld_loc['dtFields']['rst_DisplayHelpText']){
                        $fields[$idx]['dtFields']['rst_DisplayHelpText'] = $fld_loc['dtFields']['rst_DisplayHelpText'];
                    }
                }


            }
        }
    }

    //
    // read fields definition from config file
    // assign primaryField, multilangFields
    //
    private function _readFields($fields){

        //$this->multilangFields = array();

        foreach($fields as $field){

            if(!isEmptyArray(@$field['children'])){
                $this->_readFields($field['children']);

            }else{
                if(@$field['dtFields']['dty_Role']!='virtual'){
                    $this->fieldNames[] = $field['dtID'];
                }
                $this->fields[ $field['dtID'] ] = $field['dtFields'];

                if(@$field['dtFields']['dty_Role']=='primary'){
                    $this->primaryField = $field['dtID'];
                }
                if(@$field['dtFields']['rst_MultiLang']){
                    array_push($this->multilangFields, $field['dtID']);
                }
            }
        }

    }

    // need to rename temporary enity files to permanent  "entity/[entity name]/recID.png"
    // $tempfile - file to be either
    // 1) renamed to recID (if it is temp file started with ~)
    // 2) copied
    protected function renameEntityImage($tempfile, $recID, $version=null){

        $isSuccess = false;

        $entity_name = $this->config['entityName'];
        if($version==null){  //if version is defined we copy only it (icon or thumbnail)
            $version = '';
        }
        $lv = strlen($version);

        $path = HEURIST_FILESTORE_DIR.DIR_ENTITY.$entity_name.'/';//destination

        if(strpos($tempfile,'~')===0){
            //temp file is in the same folder as destination

            $directory = new \RecursiveDirectoryIterator($path);
            $iterator = new \RecursiveIteratorIterator($directory);

            foreach ($iterator as $filepath => $info) {  //rec. iteration need to copy all versions (thumb and full img)
                  if(!$info->isFile()) {continue;}

                  $filename = $info->getFilename();
                  $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);
                  //$extension = $info->getExtension(); since 5.3.6


                  if ($filename==$tempfile.'.'.$extension) {
                      $pathname = $info->getPath();
                      $tempfile_ = $info->getPathname();
                      if($lv==0 || substr($pathname, -$lv) === $version){
                            $new_name = $pathname.'/'.$recID.'.'.$extension;
                            $isSuccess = rename ($tempfile_, $new_name);
                      }
                      if(file_exists($tempfile_)){
                            unlink( $tempfile_ );
                      }
                  }
            }

        }elseif(file_exists($tempfile)){
            $path_parts = pathinfo($tempfile);
            $ext = strtolower($path_parts['extension']);

            //special case - remove file with the same name and different ext
            $ext2 = ($ext=='svg')?'png':'svg';

            if($version!=''){ //copy only icon or thumb
                $new_name = $path.$version.'/'.$recID.'.'.$ext;
                $isSuccess = fileCopy($tempfile, $new_name);

                fileDelete($path.$version.'/'.$recID.'.'.$ext2);
            }else{
                $new_name = $path.$recID.'.'.$ext;
                $new_name_thumb = $path.'thumbnail/'.$recID.'.'.$ext;
                $isSuccess = fileCopy($tempfile, $new_name) &&  fileCopy($tempfile, $new_name_thumb);

                fileDelete($path.$recID.'.'.$ext2);
                fileDelete($path.'thumbnail/'.$recID.'.'.$ext2);
            }



        }

        if(!$isSuccess){
            //Can't copy file $tempfile as  $entity_name  image
        }
    }

    //find $tempfile among temporary files in entity folder and return file info
    protected function getTempEntityFile($tempfile){
        $entity_name = $this->config['entityName'];

        $path = HEURIST_FILESTORE_DIR.DIR_ENTITY.$entity_name.'/';

        $directory = new \DirectoryIterator($path);//RecursiveDirectoryIterator
        $iterator = new \IteratorIterator($directory);//Recursive

        foreach ($iterator as $filepath => $info) {
              if(!$info->isFile()) {continue;}

              $filename = $info->getFilename();
              $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);
              //$extension = $info->getExtension(); since 5.3.6

              if ($filename==$tempfile.'.'.$extension) {
                    return $info;
              }
        }
        return null;
    }

    //
    //
    //
    protected function getEntityImagePath($recID, $version=null, $db_name=null, $extension=null){

            $entity_name = $this->config['entityName'];

            list($filename, $content_type, $url) = resolveEntityFilename($entity_name, $recID, $version, $db_name, $extension);

            return $filename;
    }

    //
    // validate duplication
    //
    protected function doDuplicationCheck($idx, $field, $message){

            if(@$this->records[$idx][$field]){
                $mysqli = $this->system->get_mysqli();
                $res = mysql__select_value($mysqli,
                        "SELECT {$this->primaryField} FROM ".$this->config['tableName']."  WHERE $field='"
                        .$mysqli->real_escape_string( $this->records[$idx][$field] )."'");
                if($res>0 && $res!=@$this->records[$idx][$this->primaryField]){

                    $sup_info = null;
                    if($this->config['tableName']=='defDetailTypes'){ //special case
                        $sup_info = array($this->primaryField=>$res);
                    }

                    $this->system->addError(HEURIST_ACTION_BLOCKED, $message, $sup_info);
                    return false;
                }
            }
            return true;
    }

    //
    // extracts records from "data" parameter and fills $this->recordIDs and $this->records
    // it is used in delete, save
    //
    //  fields:[fldname:value,fieldname2:values,.....]
    //  translation:[fldname:"lang:value",fieldname2:"lang:value",.....]
    //
    protected function prepareRecords(){
        //fields contains record data
        if(@$this->data['fields'] && is_string($this->data['fields'])){
            $this->data['fields'] = json_decode($this->data['fields'], true);
        }


        if(!is_array(@$this->data['fields']) || count($this->data['fields'])<1){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Missing 'fields' parameter. Fields are not defined");
                return false;
        }
        //detect whether this is multi record save
        if(array_keys($this->data['fields']) !== range(0, count($this->data['fields']) - 1)){
            //number of keys equals to number of entries it means single record
            $this->records = array();
            $this->records[0] = $this->data['fields'];
            //$this->recordIDs = $record[$this->primaryField];
        }else{
             //this is 2dim array
            $this->records = $this->data['fields'];
        }

        //exctract primary keys
        $this->recordIDs = array();
        foreach($this->records as $idx=>$record){
            if($this->is_addition){
                $this->records[$idx][$this->primaryField] = 0;
            }
            $rec_ID = @$this->records[$idx][$this->primaryField];
            if($rec_ID>0){
                $this->recordIDs[] = $rec_ID;
            }

            //extract translated values into separate array
            foreach($this->multilangFields as $fieldname){
                $values = $record[$fieldname];
                if(is_array($values)){
                    $mainvalue = null;
                    foreach($values as $k => $val){

                        list($lang, $val) = extractLangPrefix($val);

                        if($lang!=null)
                        {
                            if(!@$this->translation[$idx]){
                                $this->translation[$idx] = array();
                            }
                            if(!@$this->translation[$idx][$fieldname]){
                                $this->translation[$idx][$fieldname] = array();
                            }

                            $this->translation[$idx][$fieldname][$lang] = $val;

                        }elseif(!$mainvalue){
                            //without lang prefix, take first only
                            $mainvalue = $val;
                        }
                    }//values
                    if($mainvalue!=null && @$this->translation[$idx][$fieldname]){
                        $this->records[$idx][$fieldname] = $mainvalue;
                    }
                }
            }

            if(!empty($this->duplicationCheck)){
                foreach($this->duplicationCheck as $field=>$msg){
                    if(!$this->doDuplicationCheck($idx, $field, $msg)){
                         return false;
                    }
                }
            }
        }//foreach

        return true;
    }

    //
    //
    //
    public function search_title(){

        $this->data['details'] = 'name';

        $ret = $this->search();
        if($ret!==false){
            $res = array();
            foreach($ret['records'] as $record){
                //$record[0]
                $res[] = $record[1];
            }
            return $res;
        }else{
            return false;
        }

    }

    /**
     * Perform search on the database records.
     *
     * @return mixed Result of the search.
     */
    public function search(){

        if($this->isvalid()){
            $this->searchMgr = new DbEntitySearch( $this->system, $this->config, $this->fields);

            $res = $this->searchMgr->validateParams( $this->data );
            if(!is_bool($res)){
                $this->data = $res;
            }else{
                if(!$res) {return false;}
            }

            return true;
        }else{
            return false;
        }
    }

}
?>
