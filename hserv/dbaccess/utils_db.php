<?php
use hserv\utilities\DbUtils;
use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

//@TODO convert to class

    /**
    *  Database utilities :   mysql_ - prefix for function
    *
    *  mysql__connection - establish connection
    *  mysql__usedatabase
    *  mysql__create_database
    *  mysql__drop_database
    *  mysql__foreign_check
    *  mysql__supress_trigger
    *  mysql__safe_updatess
    *  mysql__found_rows
    *
    *  mysql__getdatabases4 - get list of databases
    *  mysql__check_dbname
    *  mysql__get_names - get database name with and without hdb prefix
    *
    *  mysql__select - base function
    *  mysql__select_assoc - returns array  key_column(first field)=>array(field=>val,....)
    *  mysql__select_assoc2 - returns array  key_column=>val_column for given table
    *  mysql__select_list - returns array of one column values
    *  mysql__select_value   - return the first column of first row
    *  mysql__select_row   - returns first row
    *  mysql__select_row_assoc - returns first row assoc fieldnames
    *  mysql__select_all
    *  mysql__duplicate_table_record
    *  mysql__insertupdate
    *  mysql__select_param_query
    *  mysql__exec_param_query
    *  mysql__delete
    *  mysql__begin_transaction
    *  mysql__script - executes sql script file
    *
    *
    *  getSysValues - Returns values from sysIdentification
    *  isFunctionExists - verifies that mysql stored function exists
    *  checkDatabaseFunctions - checks that all db functions exists and recreates them if they are missed
    *  checkDatabaseFunctionsForDuplications
    *  trim_item
    *  stripAccents
    *  prepareIds
    *  prepareStrIds
    *  predicateId - prepare field compare with one or more ids
    *
    *  checkMaxLength - check max length for TEXT field
    *  getDefinitionsModTime - returns timestamp of last update of db denitions
    *
    *  recreateRecLinks
    *  recreateRecDetailsDateIndex
    * 
    *  createTable
    *  alterTable
    *  hasTable - Returns true if table exists in database
    *  hasColumn - Returns true if column exists in given table
    *  checkUserStatusColumn - Checks that sysUGrps.ugr_Enabled has proper set - @todo remove
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

    /**
    * Connect to database server and use given database
    * 
    * @param mixed $dbname
    * @return a MySQL instance on success or array with code and error message on failure.
    */
    function mysql__init($dbname){
        
        //connecction parameter defined in heuristConfigIni.php
        $mysqli = mysql__connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DB_PORT);
        
        if (is_a($mysqli, 'mysqli') && $dbname){
            
            $res = mysql__usedatabase($mysqli, $dbname);
            if ( $res!==true ){
                //open of database failed
                return $res;
            }
        }
        
        return $mysqli;  
    }
    
    /**
    * Connect to db server
    *
    * @param mixed $dbHost
    * @param mixed $dbUsername
    * @param mixed $dbPassword
    *
    * @return a MySQL instance on success or array with code and error message on failure.
    */
    function mysql__connection($dbHost, $dbUsername, $dbPassword, $dbPort=null){

        if(null==$dbHost || $dbHost==""){
            return array(HEURIST_SYSTEM_FATAL, "Database server is not defined. Check your configuration file");
        }
        
        $res = true;

        try{
            $mysqli = mysqli_init();
            if($mysqli){
                //debug mode mysqli_report(MYSQLI_REPORT_ALL);
                mysqli_report(MYSQLI_REPORT_STRICT);//MYSQLI_REPORT_ERROR |
                $mysqli->options(MYSQLI_OPT_LOCAL_INFILE, 1);
                $res = $mysqli->real_connect($dbHost, $dbUsername, $dbPassword, null, $dbPort);
            }
        } catch (Exception $e)  {
        }
        if(!($mysqli && $res)){
            return array(HEURIST_SYSTEM_FATAL, "Could not connect to database server, MySQL error: " . mysqli_connect_error());
        }

        /* check connection */
        if (mysqli_connect_errno()) {
            return array(HEURIST_SYSTEM_FATAL, "Could not connect to database server, MySQL error: " . mysqli_connect_error());
        }
        return $mysqli;
    }

    /**
    * open database
    *
    * @param mixed $dbname
    */
    function mysql__usedatabase($mysqli, $dbname){

        if($dbname){

            list($database_name_full, $database_name) = mysql__get_names( $dbname );

            $res = mysql__check_dbname($dbname);
            if($res==null){
                $success = $mysqli->select_db($database_name_full);
                if(!$success){
                    $db_exists = mysql__select_value($mysqli, "SHOW DATABASES LIKE '$database_name_full'");

                    if($db_exists == null){
                        return array(HEURIST_ACTION_BLOCKED,
                            "The requested database '".htmlspecialchars($database_name, ENT_QUOTES, 'UTF-8')."' does not exist", $mysqli->error);
                    }else{
                        return array(HEURIST_INVALID_REQUEST,
                            "Could not open database ".htmlspecialchars($database_name, ENT_QUOTES, 'UTF-8'), $mysqli->error);
                    }
                }
            }else{
                return array(HEURIST_INVALID_REQUEST, $res);
            }

            //$mysqli->query('SET CHARACTER SET utf8mb4');//utf8 is utf8mb3 by default
            //$mysqli->query('SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci');
            $mysqli->query('SET NAMES utf8mb4');
            //$mysqli->query('SET SESSION MAX_EXECUTION_TIME=2000');//60000 = 1 min
        }
        return true;
    }

    //
    // Avoid illegal chars in db
    //
    function mysql__check_dbname($db_name){

        $res = null;

        if(isEmptyStr($db_name)){
            $res = 'Database parameter not defined';
        }elseif(preg_match('/[^A-Za-z0-9_\$]/', $db_name)){ //validatate database name
            $res = 'Database name '.htmlspecialchars($db_name).' is invalid. Only letters, numbers and underscores (_) are allowed in the database name';
        }elseif(strlen($db_name)>64){
            $res = 'Database name '.htmlspecialchars($db_name).' is too long. Max 64 characters allowed';
        }

        return $res;
    }

    //
    // $db_name - full databas name
    //
    function mysql__create_database( $mysqli, $db_name ){

        $res = mysql__check_dbname($db_name);

        // Avoid illegal chars in db
        if ($res==null) {
            // Create database
            // databse is created wiht utf8 (3-bytes encoding) and case insensetive collation order
            // Records, recDetails and defTerms are create with utf8mb4 (4bytes encoding) - see blankDBStructure.sql
            //
            $sql = 'CREATE DATABASE `'.$db_name.'` '
                     .' DEFAULT CHARACTER SET = utf8 DEFAULT COLLATE = utf8_general_ci';
                    //.' DEFAULT CHARACTER SET = utf8mb4 DEFAULT COLLATE = utf8mb4_0900_ai_ci';
                    //

            if ($mysqli->query($sql)) {
                $res = true;
            } else {
                $res = array(HEURIST_DB_ERROR,
                        'Unable to create database '
                            .htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8')
                            .' SQL error: '.$mysqli->error);
            }
        }else{
            $res = array(HEURIST_INVALID_REQUEST, $res);
        }
        return $res;
    }

    //
    //
    //
    function mysql__drop_database( $mysqli, $db_name ){

        return $mysqli->query('DROP DATABASE `'.$db_name.'`');
    }

    //
    // on / off foreign indexes verification
    //
    function mysql__foreign_check( $mysqli, $is_on ){
        $mysqli->query('SET FOREIGN_KEY_CHECKS = '.($is_on?'1':'0'));
    }

    //
    //
    //
    function mysql__supress_trigger($mysqli, $is_on ){
        $mysqli->query('SET @SUPPRESS_UPDATE_TRIGGER='.($is_on?'1':'NULL'));
    }

    //
    //
    //
    function mysql__safe_updatess($mysqli, $is_on ){
        $mysqli->query('SET SQL_SAFE_UPDATES='.($is_on?'1':'0'));
    }

    //
    // FOUND_ROWS function are deprecated; expect them to be removed in a future version of MySQL
    //
    function mysql__found_rows($mysqli){
        return mysql__select_value($mysqli, 'SELECT FOUND_ROWS()');
    }

    //
    // get database name with and without hdb prefix
    //
    function mysql__get_names( $db=null ){

        if($db==null){
            $database_name = HEURIST_DBNAME;
            $database_name_full = HEURIST_DBNAME_FULL;
        }else{
            if(strpos($db, HEURIST_DB_PREFIX)===0){
                $database_name_full = $db;
                $database_name = substr($db,strlen(HEURIST_DB_PREFIX));
            }else{
                $database_name = $db;
                $database_name_full = HEURIST_DB_PREFIX.$db;
            }
        }
        return array($database_name_full, $database_name);
    }

    /**
     * Returns a list of databases as an array.
     *
     * @param mysqli $mysqli - The MySQLi connection object
     * @param bool $with_prefix - Whether to include the prefix (default: false)
     * @param string|null $starts_with - Optional string to filter database names by a prefix
     * @param string|null $email - The email of the current user for role filtering
     * @param string|null $role - The role to filter by ('admin' or 'user')
     * @param string $prefix - The prefix used for database names (default: HEURIST_DB_PREFIX)
     *
     * @return array - List of database names matching the criteria
     *
     * @throws Exception - If the SQL query fails
     */
    function mysql__getdatabases4($mysqli, $with_prefix = false, $starts_with = null,
                                  $email = null, $role = null, $prefix = HEURIST_DB_PREFIX)
    {
        // Step 1: Validate and construct the `LIKE` clause for database filtering
        $where = $prefix . '%'; // Default case
        if ($starts_with && mysql__check_dbname($starts_with) == null) { // && preg_match('/^[A-Za-z0-9_\$]+$/', $starts_with)
            $where = $prefix . $starts_with . '%';
        }

        // Step 2: Execute the database query
        $query = "SHOW DATABASES WHERE `database` LIKE '" . $mysqli->real_escape_string($where) . "'";
        $res = $mysqli->query($query);

        if (!$res) {
            throw new Exception('Error executing SHOW DATABASES query: ' . $mysqli->error);
        }

        $databases = [];

        // Step 3: Filter databases based on role and email, if provided
        while ($row = $res->fetch_row()) {
            $database = $row[0];
            if (strpos($database, $prefix) !== 0) {
                continue;
            }
            $filtered_db = mysql__checkUserRole($mysqli, $database, $email, $role);
            if ($filtered_db) {
                $databases[] = htmlspecialchars($with_prefix ? $database : substr($database, strlen($prefix)));
            }
        }
        $res->close();

        // Step 4: Sort the result case-insensitively
        natcasesort($databases);
        return array_values($databases); // Re-index for JSON compatibility
    }


    /**
     * Checks that given database user has specified role
     *
     * @param mysqli $mysqli - The MySQLi connection object
     * @param string $database - The database name
     * @param string|null $email - The user's email for filtering
     * @param string|null $role - The role to filter by ('admin' or 'user')
     *
     * @return bool - True if the database matches the role and email filter, false otherwise
     */
    function mysql__checkUserRole($mysqli, $database, $email, $role) {
        if(empty($email) || !$role){
            return true; // No filtering required
        }

        $sanitized_db = $mysqli->real_escape_string($database);
        $query = null;

        // Determine the query based on the role
        if ($role == 'user') {
            $query = "SELECT ugr_ID FROM `$sanitized_db`.sysUGrps
                      WHERE ugr_eMail = '" . $mysqli->real_escape_string($email) . "'";
        } elseif ($role == 'admin') {
            $query = "SELECT ugr_ID FROM `$sanitized_db`.sysUGrps
                      JOIN `$sanitized_db`.sysUsrGrpLinks ON ugr_ID = ugl_UserID
                      JOIN sysIdentification ON ugl_GroupID = sys_OwnerGroupID
                      WHERE ugl_Role = 'admin' AND ugr_eMail = '" . $mysqli->real_escape_string($email) . "'";
        }

        $value = mysql__select_value($mysqli, $query);

        return $value!=null;
    }



    function mysql__select($mysqli, $query){

        $res = null;
        if($mysqli && $query){
            $res = $mysqli->query($query);
            if (!$res){
                error_log($mysqli->errno.'****'.$mysqli->error);
//remarked to avoid security report alert  error_log($query)
                return null;

/*
determine our thread id and kill connection
$thread_id = $mysqli->thread_id;
$mysqli->kill($thread_id);
*/
            }
        }

        return $res;
    }

    /**
    * returns array  key_column=>val_column for given table
    */
    function mysql__select_assoc2($mysqli, $query):array{

        $matches = array();
        if($mysqli && $query){

            $res = $mysqli->query($query);
            if ($res){
                while ($row = $res->fetch_row()){
                    $matches[$row[0]] = $row[1];
                }
                $res->close();
            }
        }
        return $matches;
    }

    /**
    * returns array  key_column(first filed)=>array(field=>val,....)
    *
    * @param mixed $mysqli
    * @param mixed $query
    * @param mixed $mode
    *                   0 - two dimensional array of records
    *                   1 - array of records with index from first column
    */
    function mysql__select_assoc($mysqli, $query, $mode=1):array{

        $matches = array();
        if($mysqli && $query){

            $res = $mysqli->query($query);
            if ($res){
                while ($row = $res->fetch_assoc()){
                    if($mode==0){
                        $matches[] = $row;
                    }else{
                        $key = array_shift($row);
                        $matches[$key] = $row;
                    }
                }
                $res->close();
            }
        }
        return $matches;
    }

    /**
    * returns array of FIRST column values
    * alwasys return array
    */
    function mysql__select_list2($mysqli, $query, $functionName=null):array {


        if(!($mysqli && $query)){
            return array();
        }

        $matches = array();

        $res = $mysqli->query($query);

        if ($res){
            if($functionName!=null){
                while ($row = $res->fetch_row()){
                    array_push($matches, $functionName($row[0]));
                }
            }else{
                while ($row = $res->fetch_row()){
                    array_push($matches, $row[0]);
                }
            }
            $res->close();
        }

        return $matches;
    }

    function mysql__select_list($mysqli, $table, $column, $condition):array {
        $query = "SELECT $column FROM $table WHERE $condition";
        return mysql__select_list2($mysqli, $query);
    }

    /**
    * return the first column of first row
    *
    * @param mixed $mysqli
    * @param mixed $query
    */
    function mysql__select_value($mysqli, $query, $params=null) {
        $row = mysql__select_row($mysqli, $query, $params);

        if($row && @$row[0]!=null){
            $result = $row[0];
        }else{
            $result = null;
        }
        return $result;
    }

    /**
    * returns first row
    *
    * @param mixed $mysqli
    * @param mixed $query
    */
    function mysql__select_row($mysqli, $query, $params=null) {
        $result = null;
        if($mysqli){

            $res = mysql__select_param_query($mysqli, $query, $params);
            if($res){
                $row = $res->fetch_row();
                if($row){
                    $result = $row;
                }
                $res->close();
            }else{
                USanitize::errorLog('Query: '.$query.'.  mySQL error: '.$mysqli->error);
            }
        }
        return $result;
    }

    /**
    * returns first row with assoc field names
    *
    * @param mixed $mysqli
    * @param mixed $query
    */
    function mysql__select_row_assoc($mysqli, $query) {
        $result = null;
        if($mysqli){
            $res = $mysqli->query($query);
            if($res){
                $row = $res->fetch_assoc();
                if($row){
                    $result = $row;
                }
                $res->close();
            }
        }
        return $result;
    }


    /**
    * returns all rows as two dimensional array
    *
    * @param mixed $mysqli
    * @param mixed $query
    * @param mixed $mode
    *                   0 - two dimensional array of records
    *                   1 - array of records with index from first column
    * @return []
    */
    function mysql__select_all($mysqli, $query, $mode=0, $i_trim=0) {

        if(!$mysqli){
            return null;
        }

        $result = array();
        $res = $mysqli->query($query);
        if ($res){
            while ($row = $res->fetch_row()){

                if($i_trim>0) {array_walk($row, 'trim_item', $i_trim);}

                if($mode==1){
                    $rec_id = array_shift($row);
                    $result[$rec_id] = $row;
                }else {
                    array_push($result, $row);
                }
            }
            $res->close();

        }elseif($mysqli->error){
            return null;
        }

        return $result;
    }

    //
    //
    function mysql__get_table_columns($mysqli, $table){

        $res = $mysqli->query('DESCRIBE '.$table);
        if (!$res) {return null;}
        $matches = array();
        if($res){
            while ($row = $res->fetch_row()) {array_push($matches, $row[0]);}

            $res->close();
        }
        return $matches;
    }

//
//
//
    function mysql__duplicate_table_record($mysqli, $table, $idfield, $oldid, $newid){

        $columns = mysql__get_table_columns($mysqli, $table);

        //in our scheme first column is always id (primary key)
        array_shift($columns);

        $columns3 = array();
        foreach($columns as $idx=>$column){
            $columns3[] = '`'.preg_replace(REGEX_ALPHANUM, "", $column).'`';//for snyk
        }

        if($idfield!=null && $newid!=null){

            $idx = array_search('`'.$idfield.'`', $columns3);
            $columns2 = $columns3;
            $columns2[$idx] = intval($newid);
            $columns2 = implode(',',$columns2);

        }else{
            $columns2 = implode(',',$columns3);
        }

        $where = " where `$idfield`=".intval($oldid);

        $columns3 = implode(',',$columns3);
        //
        $query = "INSERT INTO `$table` ($columns3) SELECT $columns2 FROM `$table`".$where;

        $res = $mysqli->query($query);
        if(!$res){
            $ret = 'database error - ' .$mysqli->error;
        }else{
            $ret = $mysqli->insert_id;
       }
        return $ret;
    }

    /**
    * delete record for given table
    *
    * returns record ID in case success or error message
    *
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $table_prefix
    * @param mixed $record   - array(fieldname=>value) - all values considered as String except when field ended with ID
    *                          fields that don't have specified prefix are ignored
    */
    function mysql__delete($mysqli, $table_name, $table_prefix, $rec_ID){

        $ret = null;

        $rec_ID = prepareIds($rec_ID);

        if(!empty($rec_ID)){

            if (substr($table_prefix, -1) !== '_') {
                $table_prefix = $table_prefix.'_';
            }

            $query = SQL_DELETE."`$table_name`".SQL_WHERE.predicateId($table_prefix.'ID', $rec_ID);

            $res = $mysqli->query($query);

            if(!$res){
                $ret = $mysqli->error;
            }else{
                $ret = true;
            }

        }else{
            $ret = 'Invalid set of record identificators';
        }
        return $ret;
    }


    /**
    * insert or update record for given table
    *
    * returns record ID in case success or error message
    *
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $table_prefix  - config array of fields or table prefix
    * @param mixed $record   - array(fieldname=>value) - all values considered as String except when field ended with ID
    *                          fields that don't have specified prefix are ignored
    * @param boolean $allow_insert_with_newid if true, negative record id will be abs and turns into new record id
    */
    function mysql__insertupdate($mysqli, $table_name, $table_prefix, $record, $allow_insert_with_newid=false){

        $ret = null;
        $primary_field_type = 'integer';

        if(is_array($table_prefix)){ //fields

            $fields = array();
            foreach($table_prefix as $fieldname=>$field_config){
                if(@$field_config['dty_Role']=='virtual') {continue;}
                if(@$field_config['dty_Role']=='primary'){
                    $primary_field = $fieldname;
                    $primary_field_type = $field_config['dty_Type'];
                }
                $fields[] = $fieldname;
            }

        }else{
            if (substr($table_prefix, -1) !== '_') {
                $table_prefix = $table_prefix.'_';
            }
            $primary_field = $table_prefix.'ID';
        }

        //if integer it is assumed autoincrement
        if($primary_field_type=='integer'){
            $rec_ID = intval(@$record[$primary_field]);
            $isinsert = ($rec_ID<1);
        }else{
            $rec_ID = @$record[$primary_field];
            if($rec_ID==null){
                //assign guid?
            }else{
                //check insert or update
                $res = mysql__select_value($mysqli,
                    "SELECT `$primary_field` FROM `$table_name` WHERE `$primary_field`=?", array('s', $rec_ID));
                $isinsert = ($res==null);
            }
        }


        if($isinsert){
            $query = "INSERT into `$table_name` (";
            $query2 = ') VALUES (';
        }else{
            $query = "UPDATE `$table_name` set ";
        }

        $params = array();
        $params[0] = '';

        foreach($record as $fieldname => $value){

            if(is_array($table_prefix)){

                if(!in_array($fieldname, $fields)) {continue;}

            }elseif(strpos($fieldname, $table_prefix)!==0){ //ignore fields without prefix
                //$fieldname = $table_prefix.$fieldname;
                continue;
            }

            $fieldname = preg_replace(REGEX_ALPHANUM, "", $fieldname);//for snyk

            if($isinsert){
                if($primary_field_type=='integer' && $fieldname==$primary_field){ //ignore primary field for update
                    if($allow_insert_with_newid){
                        $value = abs(intval($value));
                    }else{
                        continue;
                    }
                }
                $query = $query."`$fieldname`, ";

                if($fieldname=='dtl_Geo'){
                    $query2 = $query2.'ST_GeomFromText(?), ';
                }else{
                    $query2 = $query2.'?, ';
                }

            }else{
                if($fieldname==$primary_field){ //ignore primary field for update
                    continue;
                }
                if($fieldname=='dtl_Geo'){
                    $query = $query.'dtl_Geo=ST_GeomFromText(?), ';
                }else{
                    $query = $query."`$fieldname`=?, ";
                }
            }

            $dtype = ((substr($fieldname, -2) === 'ID' || substr($fieldname, -2) === 'Id')?'i':'s');
            if($fieldname == 'ulf_ObfuscatedFileID') {$dtype = 's';}//exception
            //elseif($fieldname == 'dtl_Value') $dtype = 'b';//exception

            $params[0] = $params[0].$dtype;
            if($dtype=='i' && $value==''){
                $value = null;
            }
            array_push($params, $value);
        }

        $query = substr($query,0,strlen($query)-2);
        if($isinsert){
            $query2 = substr($query2,0,strlen($query2)-2).")";
            $query = $query.$query2;
        }else{
            $query = $query.SQL_WHERE.$primary_field.'=?';

            if($primary_field_type=='integer'){
                $params[0] = $params[0].'i';
            }else{
                $params[0] = $params[0].'s';
            }
            array_push($params, $rec_ID);
        }

        $result = mysql__exec_param_query($mysqli, $query, $params);

        if($result===true && $primary_field_type=='integer'){
            $result = ($isinsert) ?$mysqli->insert_id :$rec_ID;
        }//for non-numeric it returns null


        return $result;
    }
    //
    // returns for SELECT - $stmt->get_result() or false
    //
    function mysql__select_param_query($mysqli, $query, $params=null){

        $result = false;

        if ($params==null || !is_array($params) || count($params) < 2) {// not parameterised
            $result = $mysqli->query($query);
        }else{

            $stmt = $mysqli->prepare($query);
            if($stmt){
                //Call the $stmt->bind_param() method with atrguments (string $types, mixed &...$vars)
                call_user_func_array(array($stmt, 'bind_param'), referenceValues($params));
                if($stmt->execute()){
                    $result = $stmt->get_result();
                }else{
                    $result = false;
                }
                $stmt->close();
            }else{
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Executes a MySQL query with optional parameters and returns the result or error.
     *
     * For `INSERT` and `UPDATE` queries, returns the affected rows or insert ID.
     * If the query fails, returns the MySQL error message.
     *
     * @param mysqli $mysqli - The MySQLi connection object
     * @param string $query - The SQL query with placeholders for parameters
     * @param array|null $params - An array of parameters, first element is a string of types (e.g., 'sdi')
     * @param bool $return_affected_rows - If true, return affected rows or insert ID (default: false)
     *
     * @return mixed - True on success, MySQL error string on failure, affected rows or insert ID if requested
     */
    function mysql__exec_param_query($mysqli, $query, $params = null, $return_affected_rows = false) {

        // Determine if the query is an INSERT operation
        $is_insert = (stripos($query, 'INSERT') === 0);
        $result = false;

        // Non-parameterized query execution
        if (isEmptyArray($params)) {
            if ($mysqli->query($query)) {
                $result = handleResult($mysqli, $is_insert, $return_affected_rows);
            } else {
                $result = $mysqli->error;
            }
            return $result;
        }

        // Parameterized query execution
        $stmt = $mysqli->prepare($query);
        if ($stmt) {
            call_user_func_array(array($stmt, 'bind_param'), referenceValues($params));

            if (!$stmt->execute()) {
                $result = $stmt->error;
            } else {
                $result = handleResult($mysqli, $is_insert, $return_affected_rows);
            }

            $stmt->close(); // Close the statement
        } else {
            $result = $mysqli->error;
        }

        return $result;
    }

    /**
     * Handles the result of the query, returning the affected rows or insert ID if required.
     *
     * @param mysqli $mysqli - The MySQLi connection object
     * @param bool $is_insert - Whether the query is an INSERT operation
     * @param bool $return_affected_rows - Whether to return affected rows or insert ID
     *
     * @return mixed - True on success, insert ID or affected rows if requested
     */
    function handleResult($mysqli, $is_insert, $return_affected_rows) {
        if ($return_affected_rows) {
            return $is_insert ? $mysqli->insert_id : $mysqli->affected_rows;
        }
        return true;
    }

    /**
     * Converts an array of values to a format suitable for `call_user_func_array`.
     *
     * @param array $arr - The array of values (first element is the types string)
     * @return array - The array with references for binding parameters
     */
    function referenceValues($arr) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key]; // Make reference for call_user_func_array
        }
        return $refs;
    }

    /**
    * Execute mysql script file
    *
    * @param mixed $database_name_full
    * @param mixed $script_file
    */
    function mysql__script($database_name_full, $script_file, $dbfolder=null) {
        global $errorScriptExecution;

        $error = '';
        $res = false;

        //0: use 3d party PDO mysqldump, 2 - call mysql via shell (default)
        $dbScriptMode = defined('HEURIST_DB_MYSQL_SCRIPT_MODE')?HEURIST_DB_MYSQL_SCRIPT_MODE :0;

        $script_file = basename($script_file);
        if($dbfolder!=null){
            $script_file = $dbfolder.$script_file;
        }else{
            //all scripts are in admin/setup/dbcreate
            $script_file = HEURIST_DIR.'admin/setup/dbcreate/'.$script_file;
        }


        if(!file_exists($script_file)){
            $res = 'Unable to find sql script '.htmlspecialchars($script_file);
        }else{

            if($dbScriptMode==2){
                if (!defined('HEURIST_DB_MYSQLPATH') || !file_exists(HEURIST_DB_MYSQLPATH)){

                    $msg = 'The path to mysql executable has not been correctly specified. '
                    .'Please ask your system administrator to fix this in the heuristConfigIni.php '
                    .'(note the settings required for a single server vs mysql running on a separate server)';

                    return array(HEURIST_SYSTEM_CONFIG, $msg);
                }
            }else {
                $dbScriptMode = 0;
            }

            //  cat sourcefile.sql | sed '/^CREATE DATABASE/d' | sed '/^USE/d' > destfile.sql
            //  cat sourcefile.sql | sed '/^CREATE DATABASE/d' | sed '/^USE/d' | mysql newdbname

            //$dbScriptMode = 0; //disable all others

            if($dbScriptMode==2){  //DEFAULT
                //shell script - server admin must specify "local" login-path with mysql_config_editor
                // mysql_config_editor set --login-path=local --host=127.0.0.1 --user=username --password

                $arr_out = array();
                $res2 = null;

                $cmd = escapeshellcmd(HEURIST_DB_MYSQLPATH);
                if(strpos(HEURIST_DB_MYSQLPATH,' ')>0){
                    $cmd = '"'.$cmd.'"';
                }

                $port = '';
                if(HEURIST_DB_PORT){
                    $port = " -P ".HEURIST_DB_PORT;
                }

                /* remarked temporary to avoid security warnings */
                $cmd = $cmd         //." --login-path=local "
                ." -h ".HEURIST_DBSERVER_NAME." ".$port
                ." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD
                ." -D ".escapeshellarg($database_name_full)." < ".escapeshellarg($script_file). ' 2>&1';

                $shell_res = exec($cmd, $arr_out, $res2);

                if ($res2 != 0) { // $shell_res is either empty or contains $arr_out as a string
                    $error = 'Error. Shell returns status: '.($res2!=null?intval($res2):'unknown')
                        .'. Output: '.(!isEmptyArray($arr_out)?print_r($arr_out, true):'');
                }else{
                    $res = true;
                }


            }else{ //3d party function that uses PDO

                if(!function_exists('execute_db_script')){
                        include_once dirname(__FILE__).'/../utilities/utils_db_load_script.php';// used to load procedures/triggers
                }
                if(db_script($database_name_full, $script_file, false)){
                        $res = true;
                }else{
                        $error = $errorScriptExecution;
                }
            }

            if(!$res){
                $res = 'Unable to execute script '.htmlspecialchars(basename($script_file)).' for database '.$database_name_full;
            }
        }

        if($res!==true){
            $res = array(HEURIST_DB_ERROR, $res, $error);
        }

        return $res;
    }
    
    /**
    * Return database version
    * 
    * @param mixed $mysqli
    * @return version or null
    */
    function getDbVersion($mysqli){
        
        $db_version = null;
        
        if (is_a($mysqli, 'mysqli')){

            $system_settings = getSysValues($mysqli);
            if(is_array($system_settings)){

                $db_version = $system_settings['sys_dbVersion'].'.'
                              .$system_settings['sys_dbSubVersion'].'.'
                              .$system_settings['sys_dbSubSubVersion'];
            }
        }        
        
        return $db_version;
        
    }
    

    /**
    * Returns values from sysIdentification
    *
    * @todo move to specific entity class
    *
    * @param mixed $mysqli
    */
    function getSysValues($mysqli){

        $sysValues = null;

        if($mysqli){
            $res = $mysqli->query('select * from sysIdentification');
            if ($res){
                $sysValues = $res->fetch_assoc();
                $res->close();
            }

        }
        return $sysValues;
    }

    /**
    * Check that db function exists
    *
    * @param mixed $mysqli
    * @param mixed $name
    */
    function isFunctionExists($mysqli, $name){
        $res = false;
        try{

             // search function
             $res = $mysqli->query('SHOW CREATE FUNCTION '.$name);
             if($res){
                $row2 = mysqli_fetch_row($res);
                if($row2){
                    $res = true;
                 }
             }

        } catch (Exception $e) {
        }
        return $res;
    }


    /**
    * This function is called on login
    * Validate the presence of db functions. If one of functions does not exist - run admin/setup/dbcreate/addProceduresTriggers.sql
    *
    */
    function checkDatabaseFunctions($mysqli){

            $res = false;

            if(!isFunctionExists($mysqli, 'getEstDate')){ //getTemporalDateString need drop old functions
                $res = mysql__script(HEURIST_DBNAME_FULL, 'addProceduresTriggers.sql');
            }else{
                $res = true;
            }

            return $res;
    }

    //
    //  NEW_LIPOSUCTION_255 is used in recordDupes
    //
    function checkDatabaseFunctionsForDuplications($mysqli){

         if(!isFunctionExists($mysqli, 'NEW_LIPOSUCTION_255')){
                $res = mysql__script(HEURIST_DBNAME_FULL, 'addFunctions.sql');
         }else{
                $res = true;
         }

         return $res;

    }


    //
    //
    //
    function recreateRecLinks($system, $is_forced)
    {

        $mysqli = $system->getMysqli();

        $res = true;
        $is_table_exist = hasTable($mysqli, 'recLinks');

        if($is_forced || !$is_table_exist){
                //recreate cache
                if($is_table_exist){

                    $query = "drop table IF EXISTS recLinks";
                    if (!$mysqli->query($query)) {
                        $system->addError(HEURIST_DB_ERROR, 'Cannot drop table cache table: ' . $mysqli->error);
                        $res = false;
                    }

                }else{
                    //recreate triggers if recLinks does not exist
                }
                if($res){

                    $res = mysql__script(HEURIST_DBNAME_FULL, 'addProceduresTriggers.sql');
                    if($res===true){
                        $res = mysql__script(HEURIST_DBNAME_FULL, 'sqlCreateRecLinks.sql');
                    }
                }

                if($res!==true){
                    $system->addErrorArr($res);
                    $res = false;
                }

        }
        return $res;
    }

    //
    // $need_populate - adds entries to recDetailsDateIndex
    // $json_for_record_details - update recDetails - change Plain string temporals to JSON
    //
    function recreateRecDetailsDateIndex($system, $need_populate, $json_for_record_details, $offset=0, $progress_report_step=-1){

        $mysqli = $system->getMysqli();

        $dbVerSubSub = $system->settings->get('sys_dbSubSubVersion');

        $isok = true;
        $is_table_exist = hasTable($mysqli, 'recDetailsDateIndex');

        $err_prefix = '';
        $cnt = 0;
        $cnt_all = 0;
        $cnt_to_json = 0;
        $cnt_err = 0;
        $report = array();

        $log_file = $system->getSysDir().'recDetailsDateIndex.log';

        if($offset>0){
            $res = true;
        }else{
            $mysqli->query('DROP TABLE IF EXISTS recDetailsDateIndex;');
            $res = $mysqli->query("CREATE TABLE recDetailsDateIndex (
                  rdi_ID   int unsigned NOT NULL auto_increment COMMENT 'Primary key',
                  rdi_RecID int unsigned NOT NULL COMMENT 'Record ID',
                  rdi_DetailTypeID int unsigned NOT NULL COMMENT 'Detail type ID',
                  rdi_DetailID int unsigned NOT NULL COMMENT 'Detail ID',
                  rdi_estMinDate DECIMAL(15,4) NOT NULL COMMENT '',
                  rdi_estMaxDate DECIMAL(15,4) NOT NULL COMMENT '',
                  PRIMARY KEY  (rdi_ID),
                  KEY rdi_RecIDKey (rdi_RecID),
                  KEY rdi_DetailTypeKey (rdi_DetailTypeID),
                  KEY rdi_DetailIDKey (rdi_DetailID),
                  KEY rdi_MinDateKey (rdi_estMinDate),
                  KEY rdi_MaxDateKey (rdi_estMaxDate)
                ) ENGINE=InnoDB COMMENT='A cache for date fields to speed access';");
        }
        
/*
CREATE TABLE recDetailsEnumIndex (
                  rdi_ID   int unsigned NOT NULL auto_increment COMMENT 'Primary key',
                  rdi_RecID int unsigned NOT NULL COMMENT 'Record ID',
                  rdi_DetailTypeID int unsigned NOT NULL COMMENT 'Detail type ID',
                  rdi_DetailID int unsigned NOT NULL COMMENT 'Detail ID',
                  rdi_Value int unsigned NOT NULL COMMENT 'Enum value',
                  PRIMARY KEY  (rdi_ID),
                  KEY rdi_RecIDKey (rdi_RecID),
                  KEY rdi_DetailTypeKey (rdi_DetailTypeID),
                  KEY rdi_DetailIDKey (rdi_DetailID),
                  KEY rdi_MinDateKey (rdi_Value)
                ) ENGINE=InnoDB COMMENT='A cache for enum fields to speed access';
                
insert into recDetailsEnumIndex (
                  rdi_RecID,
                  rdi_DetailTypeID,
                  rdi_DetailID,
                  rdi_Value) 
SELECT dtl_RecID, dtl_DetailTypeID, dtl_ID, dtl_Value FROM recDetails, defDetailTypes where dtl_DetailTypeID=dty_ID and dty_Type='enum';                

"48186,63333,62803,62677,63156,62956,63418,63298,62854,63266,51089,77962,63299,63201,63503,62921,62894,63369,50738,63423,63271,63335,62690,63340,63039,62739,63303,63462,63381,62726,62936,62652,63168,63257,63232,63056,63424,63147,63126,63009,63494,63189,62874,62985,63342,63411,63088,62909,63323,63207,63107,63010,63105,63351,62766,63050,63359,63233,62945,63343,62951,63318,63429,62727,63408,62866,62861,62742,62735,62743,63425,63511,63426,63446,63382,63501,63489,63450,62655,63401,63328,63300,68625,63380,63419,62653,62929,62656,62664,63042,62977,63099,62785,62768,63474,62703,62883,62934,63374,49545,63166,63365,63007,62675,63165,62935,63272,62686,63279,62697,62744,63238,63427,63047,62800,63420,63326,63095,63458,63383,62797,62823,62657,62708,62988,63428,62719,62730,63280,63197,63464,63245,62914,63336,62741,63135,62695,62793,62745,63360,62746,63375,62752,62765,62801,63344,62658,47145,63145,63502,63465,63345,62802,62725,62850,62711,62764,62786,63301,62965,62775,63194,63371,63490,63282,63384,62805,63283,63101,62873,63118,62958,62731,63409,62750,63113,62704,63367,63362,62749,63278,63254,62698,63128,62778,63251,62843,63447,48066,63186,63386,63161,62847,63137,62960,62983,63061,62845,63431,63032,62943,62903,62882,62728,62989,63373,63239,63319,62808,62662,62830,63237,62729,63281,63217,63214,63498,62904,62780,62687,63376,62701,63181,63122,63372,63150,63466,63364,62937,63143,63510,63081,63433,62880,63160,62858,62723,62824,63317,63396,63063,49943,63136,62862,63284,62835,62670,63346,63139,47446,63034,63387,62902,62737,63053,48928,63044,62738,63459,62931,47882,63037,62724,63132,62767,62787,62663,62972,62980,63463,63480,63445,62705,62740,63002,62932,63467,62969,63153,62832,62837,62783,63388,63020,62827,62863,63417,62733,62834,62751,63406,63412,62706,63322,62776,63361,63140,63389,63402,62748,63449,63286,63500,63505,62754,62707,62774,63040,63390,63432,63320,62755,63231,44104,45468,50849,62875,63302,62732,62819,63025,63377,63366,63055,63454,63468,62666,63102,49600,62886,63111,62709,63460,62661,63461,63491,63110,63287,63415,62923,63379,63448,62667,63338,63330,62668,63288,62760,63321,62693,63157,62807,62879,62784,63001,49167,62954,62865,63443,62759,63258,62916,62901,62815,63036,62715,62897,63142,63508,63410,63434,62747,62968,62860,62908,62829,63400,62769,47267,63202,63164,62973,62910,62757,62846,62799,63206,62925,62919,62804,63414,62853,62905,62946,63435,63016,62947,62669,62906,63173,63183,62930,62941,63495,63289,62961,62814,63188,62683,62849,63209,63006,62895,63249,62999,62753,63260,63005,63348,49168,62911,62856,62833,63270,63184,63268,62836,63391,62826,62770,63442,62952,63089,63290,62982,63033,63193,62963,63250,63499,63488,62692,62671,62998,62992,62917,63436,63133,62712,63337,47790,63493,50736,47588,47589,47590,47592,63392,62974,62700,62970,63077,62713,62714,51114,62867,62736,62761,63229,63155,63012,62964,63014,62887,63031,63182,62971,62773,63327,62771,63000,62997,63003,62772,63370,49548,62967,63029,49000,63203,63106,62710,63349,63058,63393,63134,49536,62878,63008,63394,62987,63115,47239,47277,47316,62821,63210,63469,63334,63011,62673,63141,63191,62938,63171,62948,63172,63405,63357,62957,62888,63291,62810,63035,63087,63120,63018,63195,63117,63304,62975,62868,62676,63091,63104,62859,63253,63200,62962,62981,62918,63090,63030,63019,63305,62678,63363,63484,63439,63021,63255,62779,62812,62790,63306,62942,62966,63028,62654,42872,63083,62848,62891,63252,63121,62924,63138,62881,63292,63308,62922,63422,63277,63086,62857,63339,63041,62939,62842,63309,63497,63456,62841,63057,47907,62899,63052,63236,63509,62763,63096,62818,63262,63310,63023,63470,63148,63178,63059,63180,62825,63471,63051,63472,63350,63395,63451,63187,63496,63404,63170,63473,63074,63358,62978,62734,63256,63487,63167,63475,63437,63123,63385,62955,63015,62953,63485,63267,62660,63159,62838,63269,63082,63476,62781,63477,63154,62928,49895,63192,62817,62665,62672,62674,62679,62681,62659,62839,63293,62944,63453,62855,63080,63294,62896,63151,63235,62840,63027,63085,49896,63368,62777,63211,62809,63416,62884,62871,77626,77698,77779,77786,77814,63097,62820,63049,63108,63094,62870,63060,63092,62912,63149,62979,63119,63403,63311,63378,62852,63275,63130,63276,63397,63093,62680,63163,63174,63131,63100,62949,63506,62828,47682,62782,63438,62682,62900,62976,62789,62993,62950,78708,62915,48133,62816,63114,62716,62991,62885,62927,62869,63146,63116,62876,62913,62994,63198,63352,63103,63353,62996,63190,63045,62984,63240,63205,62831,63398,62794,63185,63196,63312,63430,63441,62959,63124,63208,62933,63219,63098,63455,63354,62684,62890,63129,62920,62892,62791,62717,62995,63127,62792,63230,62940,62699,62718,63199,63313,62795,62893,63241,63478,63162,63234,62720,62877,47794,62926,62796,63507,62851,63479,63125,47121,62986,62798,63481,62685,63407,63017,63355,63329,49769,63046,62822,62694,63212,62721,63482,63024,63013,63026,62756,63347,63440,63452,63273,63004,63399,63457,62990,63295,63263,63022,63314,62811,62864,62688,62813,63265,63259,62907,62758,62898,50805,63204,63332,9367,62689,63274,63285,63112,63084,63220,63296,63307,62806,63264,63486,63315,50807,63504,63038,63152,63109,62788,62696,63213,63215,63216,63218,49942,63144,62762,62844,62722,63243,62889,63483,63297,63261,63054,63356,63325,63179,62872,63331,63324,63158,63341,63316,63242,63048,63043,62691,63444,63492,40964,42476,84101,75444,75470,75471,75472,75474,75483,75459,40160,42695,45679,44620,50250,48505,48734,47905,40993,42594,42675,48733,44329,43756,44299,44809,45466,46196,47604,76458,75931,50535,76038,75953,75991,84112,41248,75796,9374,40645,44999,47117,78460,82334,83719,84245,84797,84847,84988,85130,77677,85329,84929,75950,83316,76222,50652,46614,46668,45726,47553,49380,42839,49787,43638,78169,78223,78431,79000,79307,79362,79481,80417,81928,83761,84349,84660,85364,80127,85094,41551,78756,78780,78799,48184,78826,48854,49252,43782,43713,39776,8784,47917,81623,83920,49977,51444,75807,75797,40236,40571,45776,47442,48511,50972,82357,81603,44715,45619,49453,47407,50852,49249,49248,51035,45729,47115,47464,47986,48512,48851,78394,40815,44509,44142,81176,49501,50268,40014,78190,80654,80718,80884,82808,82833,82912,83519,83795,85388,85535,79268,82636,82500,50214,78992,80406,82100,83933,81722,43574,48231,45630,49108,45728,44146,41051,46088,49166,50493,40806,85445,79537,85340,39980,40653,41935,41812,41004,48850,81591,81545,83878,83644,83813,83365,81681,45400,46223,40043,42321,42182,42568,42808,44738,46776,42189,45649,46893,47497,48225,43164,47419,80665,80716,80726,80741,80760,80824,80854,81504,81742,81902,82019,82076,41865,44575,42693,40809,41558,49368,39430,44297,41614,46922,39266,39353,39521,40297,40886,41185,50257,40260,41220,42560,42645,50988,47118,47207,47332,47335,47363,40622,75587,75656,48431,80481,47564,47605,49768,80453,80497,80562,80368,39779,45777,41432,40088,45779,48194,43490,45661,47581,47582,47583,47584,47608,48064,48509,48849,49251,47453,50213,77515,77306,77502,77523,77527,49648,45563,48913,39261,41281,39705,48008,41385,41428,51326,49788,79757,79831,79968,79979,80011,80248,82876,83082,80111,39130,41048,39176,46489,48506,50410,39513,48408,84028,47114,49255,46906,46199,46597,44573,45230,46041,46350,46393,46484,43447,46392,41465,84104,46109,46505,47014,47065,83426,43944,44849,82167,45017,42945,79859,44242,84008,41851,45858,51418,79521,80928,44749,39118,44823,45350,47113,47409,47519,47609,48510,48848,77986,82206,82242,83455,83472,83592,83664,83690,83746,83764,83848,83858,83870,83921,78007,79379,49362,41345,44646,79762,40606,40130,40847,39572,40661,77624,41887,50691,78253,84098,46709,46721,47275,84685,41357,47397,47406,47530,47597,47607,48513,75330,75332,75529,75849,78199,78360,78432,78470,78471,78599,79977,80170,80322,80324,80610,80986,81015,81091,81113,81177,81295,81629,81743,81761,81817,82244,82248,82987,83037,85238,85600,44766,45616,46999,40842,40179,40859,84641,39256,46412,46732,47475,47486,47575,48504,48853,49464,47838,40824,40831,43343,45778,48855,77571,77872,50128,42716,41155,40708,41797,84018,83931,76739,50959,50136,42722,40978,49170,39696,81061,41998,42273,43196,43819,44799,44843,40864,50832,44905,45945,46106,47408,47997,43660,47116,47606,50133,82404,82414,82448,82561,82541,82801,40674,41536,42524,42959,44226,44466,44533,44730,44741,50642,84077,45862,47508,48183,39273,50917,40371,39869,41521,45360,46456,47542,48130,51265,76407,76740,77387,51266,77595,42359,48140,76983,79228,85602,76642,42451,84636,42995,80575,80600,81811,81819,81847,81907,81997,82133,82143,82379,82388,82409,82433,82447,82529,82552,82570,82584,82589,82601,82643,82653,82667,82675,82758,82792,82975,82993,83014,83079,83086,83120,83166,83211,83239,83242,83277,84622,46970,43457,47412,50763,83146,83155,83180,83542,83685,83698,84747,85259,49990,43668,80201,39615,48430,46998,43174,78397,80387,50142,50143,51189,51424,45354,47937,40858,43061,41446,80465,40534,40719,44128,49201,39996,45671,46035,46128,47763,42868,39111,41249,44391,78699,50811,81975,41136,9209,9231,49385,41919,43859,39237,51287,75897,48858,78414,78462,78533,78574,78579,78586,78613,78622,78638,78702,78712,78740,78791,79167,79413,79437,79614,79729,80034,80755,81041,81403,82886,85577,50144,51190,81988,47919,76838,84892,85116,47611,45558,51276,76854,79758,81030,81297,82412,40861,78993,49513,49517,78409,47415,78019,44316,45781,40739,78164,80039,83805,83939,84394,46722,82677,82700,82727,82785,79402,39248,81266,81625,82228,39455,82021,83643,83688,83804,83995,40699,50909,76625,76745,77305,78395,78448,81065,82505,83238,76793,76980,40001,39202,46236,82317,49894,82309,82386,40451,44522,42333,42561,44220,44892,44889,48740,48861,79942,40867,47618,79765,45356,45812,46710,48063,49254,49805,76810,81374,83526,8788,49791,43037,39874,75536,76489,80253,80347,81084,83625,83771,75155,75225,75226,75231,75236,75313,75316,75323,75354,75375,75482,75498,75501,75509,75550,75570,75609,75667,75674,75680,75681,75699,75813,75933,76009,76087,76116,76164,76237,76249,76253,76259,76280,76359,76364,76399,76427,76499,76548,76561,76563,76577,76581,76588,76598,76616,76678,76697,76721,76728,76736,76741,76772,76774,76781,76788,76800,76809,76823,76891,76899,76926,76933,76950,76952,76964,76970,76982,76998,77029,77140,77154,77161,77169,77177,77178,77194,77198,77254,77280,77304,77316,77427,77448,77491,77526,77529,77576,77588,77589,77590,77621,77629,77649,77687,77718,77723,77725,77729,77733,77738,77742,77760,77764,77771,77800,77802,77808,77827,77831,77840,77846,77873,77877,77885,77888,77890,77902,77913,77915,77955,77963,77985,78011,78042,78047,78053,78059,78073,78085,78088,78101,78103,78107,78131,78163,78181,78212,78220,78254,78267,78280,78289,78317,78321,78342,78346,78351,78473,78478,78487,78502,78538,78564,78618,78623,78630,78724,78757,78829,78940,78990,79018,79033,79091,79103,79137,79171,79199,79245,79315,79338,79386,79436,79444,79463,79471,79541,79606,79635,79704,79719,79731,79749,79872,80072,80176,80183,80193,80209,80211,80221,80263,80532,80580,80589,80644,80748,80759,80798,80813,80990,80994,81010,81020,81038,81049,81057,81060,81079,81132,81143,81208,81367,81460,81602,81680,81683,81799,81963,81983,82039,82117,82134,82271,82340,82641,82650,82665,82732,82759,82786,82905,82968,82996,83036,83065,83139,83227,83232,83362,83386,83400,83437,83539,83552,83651,83730,83880,84023,84072,84109,84154,84162,84179,84294,84370,84383,84412,84451,84514,84538,84544,84581,84638,84649,84657,84669,84675,84695,84707,84735,84740,84748,84753,84761,84896,84937,84949,84971,84998,85032,85163,85254,85320,85371,85471,85484,85502,85529,85542,85561,85567,85582,85585,85601,75207,48540,48867,51036,82083,41339,46341,41137,41941,42502,43079,44354,47039,75618,81395,82002,45721,47055,47619,48139,56854,40353,79939,81047,81584,82791,85503,82715,82740,79077,40487,49382,47423,50897,75199,75281,75700,75716,75792,75959,77464,77603,77702,77767,77874,78004,78067,78134,80091,80637,80668,80723,80731,80768,81168,81897,82166,82181,82443,82586,82613,82632,82847,83076,83084,83092,83130,83214,83253,83340,83381,83392,83550,83814,84003,84089,84755,85061,81130,81933,82790,49250,39283,40048,83777,40764,78201,81250,81274,81285,81311,81329,81409,81419,81431,81440,81732,81769,82218,82245,82263,82279,82285,81546,40326,44039,44060,44117,44118,45532,47941,47621,50541,80719,83063,49499,45774,43066,40805,43141,45584,47855,47857,41883,48744,46458,47947,42567,51037,39341,43466,43902,45340,45349,47122,45933,49110,78003,83022,45089,43732,47158,82053,82982,40054,40184,42058,39974,42037,44665,42215,42069,44157,44761,44877,45094,45107,45129,46529,46944,46960,45013,40139,41588,41778,41905,41906,43913,43936,44585,46447,40882,46786,48874,46175,46486,41723,44645,76388,76392,76469,76804,78624,42162,77253,77126,48041,44778,41141,76785,77781,77848,75634,80671,82096,82848,83397,83325,51038,45348,49502,45577,39193,46111,47136,49599,39342,40057,40194,44103,44907,45516,44951,43614,39997,40611,83367,50855,42059,41785,41606,83893,39097,40760,39109,41338,40889,48598,49258,50746,43015,42364,83175,83555,83590,42331,50310,40845,39767,46161,46408,46682,44937,46954,39734,40186,78914,79875,79392,79490,50767,50785,43564,44774,43019,43932,45515,47050,43501,43840,45531,47974,49383,49387,49388,49389,49423,49424,49433,49438,49439,49441,49444,49465,49479,76430,41334,40383,49802,49804,49511,42287,50501,75411,75413,75423,84509,84254,40873,46544,75229,75240,75296,75340,75362,75418,75440,75507,75518,75573,75651,75662,75695,75729,76640,76684,76751,76806,76818,76973,77117,77184,77239,77250,77273,77377,77379,77481,78127,78138,76607,46159,47664,45352,45991,39350,79431,79257,46239,43683,39374,44864,44108,43630,48019,40111,39660,40052,44330,44587,50269,50693,85257,41987,40909,45712,42587,41476,42139,42836,50787,42689,49528,49789,49792,49793,49794,49987,76732,83636,83680,43007,47422,40017,41251,42044,42837,42867,43506,43745,43746,44163,47005,50289,50373,50377,50383,50390,50393,50555,50561,50571,50598,50612,78148,47952,48742,82352,82130,45490,46743,40947,45052,44796,44812,44893,44581,42962,49534,78881,80615,45029,45547,46502,41414,76789,78222,79155,79844,80074,80197,81190,82994,84662,84703,84710,84760,84890,85064,85188,44342,84922,84898,48865,48868,84942,49445,49446,49452,49457,49460,49461,46751,39390,50856,76002,76023,76037,76050,76112,76115,76190,76225,76233,84133,84186,84255,84267,76156,41314,42078,43420,47132,41356,40367,41133,41829,43080,44156,44159,44346,44477,47629,39114,82767,82296,51039,50196,50259,50300,50328,50329,50337,50355,50369,50374,50418,50425,50471,50478,50483,50488,50536,50559,50620,79036,79075,79446,79453,79503,79524,79733,79920,80007,80062,80077,80627,81471,82163,82508,82730,82989,83049,83354,83742,83902,83964,84419,85400,50215,49375,44512,51002,50156,51206,41958,41192,45446,42790,43424,42969,43679,44933,42686,81726,46258,40881,41946,45880,47830,47852,48085,51261,41842,47146,46871,43195,9120,42267,50152,44911,41584,41058,45359,45899,48030,48052,48515,49405,49485,49488,49489,49490,49491,49493,49494,81538,81677,81503,39774,41278,50030,47159,83596,39395,51302,40581,43586,45426,46724,49790,79086,79117,79478,79669,80573,82089,82949,83267,83287,83440,83536,83553,83631,83679,83872,85294,41754,42279,40580,40956,43566,75610,78963,79376,82574,82615,82922,83579,83626,83675,77695,41528,39424,41996,44763,44948,45172,45177,46363,46566,46570,46992,46286,46513,46546,40750,43041,45553,46206,47074,47884,50154,80638,84145,85494,50151,49371,46385,44586,50812,43795,47870,41762,43269,75319,84125,41009,40987,39985,41031,39190,39096,40207,40833,40834,46807,76276,76290,76377,76448,76488,76550,76569,76574,76676,76715,76941,76942,76956,77000,77007,77119,77136,77218,77230,78294,78837,78899,78983,80078,80161,80382,80503,80648,83593,84239,84274,84583,84686,84756,84798,84879,84887,84888,84889,84904,84909,84943,84979,84997,85007,85012,85034,85071,85092,85111,85112,85126,85133,85152,85156,85339,85466,85532,76847,42283,84931,77160,80132,40385,80698,80636,80647,41006,46801,42285,81753,82112,43384,43587,43708,44827,46657,48537,75857,46000,39453,40411,40412,41060,42047,43205,43400,43648,43827,43964,44120,44953,46438,48735,51175,51176,51308,75631,75657,75786,75848,76540,76895,77616,78221,78248,78761,78787,79016,80566,80952,80963,81039,81279,81424,81550,81864,81940,81990,82005,82124,82150,82184,82220,82238,82294,82476,83005,83105,83169,83191,83205,83252,83276,83378,83384,83418,83538,83772,83828,83961,84369,85208,85236,80272,80539,44387,41221,41062,49353,49442,49106,40368,82606,50174,43305,81795,39889,82213,82364,83683,51419,40410,75614,42755,79514,39470,50217,40977,41501,47044,50910,39587,41853,49147,47124,76057,76068,76078,76093,76161,76210,79496,79518,81220,84157,41578,39600,40973,44649,42744,79532,44629,41692,43029,39195,48869,75300,49807,49809,49980,49981,51420,75317,49806,51383,51391,51405,50187,50157,39320,39402,81923,42184,46094,49886,79553,41947,42669,46245,80277,81487,81537,81849,81854,82041,82544,82749,82943,83830,83832,41571,50247,84017,47832,50059,40906,41404,42632,40962,51080,80530,49696,75884,40523,40854,41477,41799,45198,46318,47930,49431,42588,40173,49231,50944,41454,44065,44854,46034,46845,47062,47908,49390,49429,49448,49451,49455,49456,49466,49472,50271,76257,76632,77162,78421,78994,85378,85392,78972,49411,43237,78386,41195,49100,51040,76270,42887,47617,45947,44022,45215,45480,47786,50147,51191,79752,79730,42892,39404,40681,78158,78900,78904,78921,78953,78989,78998,79299,79689,80416,80463,80537,80687,80737,80774,80814,80857,80900,80935,82121,82622,83274,85415,85558,40683,48138,43051,45951,47130,47630,47641,47963,48518,48520,48545,48547,48548,48743,48857,49202,39236,46340,49537,78318,78348,78367,78501,78513,78520,78522,78531,78545,78566,78582,78629,78643,78675,78692,78755,80509,49199,9121,45010,48516,45631,42735,45834,46347,46524,48736,48864,41064,83608,83779,83754,50788,43294,43438,43439,50533,46160,45493,46615,47875,81056,41348,44372,43213,43243,45344,45713,49533,49535,40275,47697,47417,50874,40887,49538,49544,51042,80006,75628,78240,78277,78290,78327,78356,78530,78611,78688,78753,78820,78991,79197,79371,79435,79493,79535,79835,80157,80226,80243,80257,80270,80303,81383,81539,81693,81767,81805,82072,82268,82307,82349,82359,82471,82800,82872,83122,83150,83438,83586,84161,84716,84861,85333,85385,85405,85436,78769,83687,78439,46360,75234,39161,41705,41502,50890,39181,47625,46105,45769,46352,49983,49500,81251,83068,41737,43695,42216,41013,50234,40458,41466,42439,50857,41023,42034,40489,43431,39319,39566,39567,79747,79764,79804,79815,79912,80032,80043,80060,80099,80140,80389,80393,80466,80478,80496,80529,80545,80565,80581,80596,80621,80666,80808,80811,80822,80842,80849,80886,80912,80919,80943,80958,80967,80998,80442,47157,43726,48191,48739,48859,42745,44336,49162,45578,48528,49503,49505,49988,49989,49498,42725,45287,46411,46414,78193,80926,41707,85258,81200,81497,43617,47920,44858,50643,84718,84765,84796,40027,85384,85516,82639,78849,46561,46563,41474,47653,48539,79196,84880,45100,40994,44920,41043,51233,45081,43217,48067,48142,48533,40106,43252,45760,45971,46024,46381,48799,49516,49947,50520,50522,50528,50538,50539,50543,50547,50556,50576,50622,51110,51423,75851,77402,77455,76917,80320,78488,78535,78589,77388,50814,77339,50145,50140,50747,51253,49391,51329,49968,42721,79405,79592,80800,80820,80821,80981,43856,50958,39788,40500,43615,44903,51043,41069,39159,39331,44123,46324,46325,46330,46427,46995,78388,78967,79122,80475,81880,82668,83673,83712,83823,84009,79819,80407,80470,80437,81423,40811,41738,40469,41596,39144,42174,83451,40538,39778,82499,40564,40863,41649,40675,78242,49169,39899,42108,43373,46328,48068,49257,47686,50335,50347,50378,50380,50391,79277,79342,80699,83245,83266,83792,80679,40484,42305,39364,39711,39171,40789,50934,42288,44069,44876,45158,45316,45541,46434,46519,39131,41409,79583,39204,39960,41532,39375,39654,79034,80649,81162,49810,77086,39142,39553,76299,84736,44052,39104,78209,78233,78467,78474,78482,78499,78616,78896,78901,78917,78942,78975,79008,79021,79044,79063,79076,79082,79085,79092,79099,79114,79120,79132,79180,79378,79381,79430,80104,80117,80122,80133,80332,80350,80404,80438,80769,80802,80830,80840,80904,80925,80957,80973,80984,80989,80991,81008,81170,81184,81198,81224,81301,81400,81540,81543,81569,81587,81609,81612,81624,81647,81711,81763,81844,82103,82281,82290,82336,82431,82459,82507,82559,82577,82605,82647,82707,83002,83007,83012,83018,83021,83029,83310,83339,83436,39493,42112,44341,39924,84728,84787,84653,84659,41201,42251,76314,84830,43512,40022,41548,49520,49525,49526,49799,44256,43553,77083,79311,79544,83072,83291,83517,83543,83677,83782,84002,84022,84257,83834,43118,42883,50940,47628,47424,49356,48521,48524,48529,48712,78200,39676,45635,47017,78995,79079,79546,81163,85475,81415,46829,78165,78174,78252,79093,79287,79368,79516,79551,79678,79784,79917,81076,85377,85447,85474,85482,85508,85515,85523,44192,44193,44995,45293,45881,45883,45885,46207,46586,42031,41282,41007,45655,51275,44014,82004,40143,45638,46540,42967,44259,44520,45890,47764,48532,48746,48870,47134,47148,47152,47160,48862,45670,45853,39250,40414,41781,41945,41972,42098,43629,51321,48135,81160,42221,45145,46936,48856,41711,40959,43508,45648,47819,45045,50834,45542,49237,44166,45783,46168,42948,43097,44500,44789,9132,8764,48985,50057,50058,40782,47624,50786,47418,40189,43134,44032,44268,45498,46374,46397,46525,79057,79487,79671,43398,42463,43544,44474,49203,45892,46284,76033,76720,83836,42237,82763,82789,39573,40790,49194,42456,40636,43304,45162,45194,45675,45686,45749,47096,47175,47985,48535,48538,48542,48619,48697,49510,79032,79334,42637,47675,44182,44742,43258,45929,46814,41918,41920,42927,40558,9535,41037,50026,48525,48526,78567,78593,79548,83046,83984,85576,85016,79986,39473,47057,78326,78403,78475,78687,78846,78981,79019,79207,79584,81138,81601,81606,81950,82086,82694,83044,83159,83312,83753,84024,85039,85342,85357,85440,85451,85485,84075,43972,80283,42903,78665,44988,49171,40950,40904,47127,47616,47147,47155,47798,39221,40050,51371,75159,75164,75170,75171,75243,75276,75277,75282,75290,75305,75309,75346,75376,75387,76073,77040,77350,77394,77405,77430,77444,77459,77463,77473,77498,77509,77514,77520,77521,77541,77549,77565,77566,77573,77579,77582,77601,77608,77627,77648,77652,77724,77801,77819,77821,77851,77853,77859,77866,77869,77871,77878,77880,77881,77882,77891,77892,77893,77895,77897,77898,77899,77901,77904,77908,77910,77911,77912,77914,77918,77920,77921,77922,77924,77926,77932,77936,77941,77960,77968,77970,77978,77980,77987,78037,78043,78051,78076,78081,78110,78111,78115,78125,78137,78139,78140,78333,77705,77634,39497,42518,9419,39514,41134,43729,43761,43812,43817,43866,44243,39203,75954,75982,75999,76100,76149,76212,79943,80460,84175,84176,84177,84185,84246,84320,84362,84367,84390,84392,77226,41511,60082,79480,42172,43445,43356,46498,44397,83357,83692,39235,42509,79024,79408,80522,49979,40692,40693,40694,40695,40698,40835,40836,41350,46598,44285,50141,50227,50238,50299,43914,50348,39655,41078,51044,39541,39819,40838,41170,41304,41744,41791,41923,42384,43394,43631,43788,43967,43991,44049,44322,44709,44712,45009,46756,41303,42163,39642,47414,42304,49515,49529,49765,50158,51199,51200,76471,83456,83565,84015,85108,84055,49719,39649,39984,78512,41831,46770,48078,39241,39246,39875,41834,48069,45615,47886,48070,49256,48502,44073,44894,45459,47775,48503,45582,45791,40566,78969,79109,79113,79301,78922,79015,47410,43339,46116,50960,51003,39314,47741,40691,44005,46522,50150,51422,81256,81589,50149,39474,39152,39217,48546,45072,75896,46470,41567,50477,44112,45208,46271,46300,44296,47971,48049,47967,47989,48201,48527,48738,39582,45879,46136,46170,47708,48860,49984,48143,46270,49419,47610,47626,49504,49531,49532,49539,83208,84085,42147,81841,81789,49798,77112,78256,80269,45907,49509,77240,39234,39359,76972,76976,77147,77157,78156,78179,78183,78204,78228,78272,78274,78307,78364,78399,78459,78508,78532,78552,78649,78672,78795,79358,80158,80713,81012,81078,81205,81258,81264,81349,81386,81427,81469,82102,82227,82316,82693,82878,83467,83571,83883,83950,83994,84127,84150,84283,85205,85370,85414,85420,85429,85464,85479,85501,85514,85521,85563,79218,78288,77153,77125,78211,43395,43741,44064,44745,45099,45131,45132,45184,45253,45310,45641,45959,46055,46180,46222,46483,46560,48530,49506,42344,48514,47975,43472,77087,47156,46095,42529,42900,78634,78959,79073,79089,79106,79156,79383,79549,79605,79616,79625,79640,79666,79755,79785,80281,80445,80625,40137,39211,49111,40706,41252,41517,41522,41752,41764,41766,41775,43031,43266,43642,43727,44480,44983,44984,45247,46537,46604,46728,46602,43858,48930,83787,46889,45840,50768,40289,39254,42200,44037,46087,81979,50961,39508,83635,41076,49527,49547,41233,49112,83768,47129,47623,47627,48747,49800,49801,49803,47126,43489,40582,39366,81017,81704,9114,46704,49449,49459,49462,49463,49467,49468,49469,49480,49484,42740,43960,39334,77089,43345,45186,45203,79664,79703,79885,79934,79958,50615,40568,50644,40460,41171,43573,43962,44967,45069,45179,49524,43593,41546,47600,47601,47602,47603,48071,48145,47598,49148,50506,42255,48074,40239,49144,83469,83499,83970,83479,41135,42045,43246,47069,47752,44158,45827,39419,42087,42088,45736,45772,47841,48522,39580,41934,42349,46594,41499,44442,83862,81740,75555,45860,41070,45632,46275,40588,83977,82485,40327,44058,81879,45828,46610,41040,41703,42171,44357,50460,50473,50527,50551,50554,50557,50560,50563,50603,50610,50631,49974,40844,79192,82858,40395,42678,42824,43082,39138,39710,47138,46518,41444,47413,47633,48072,49132,49796,51082,76486,40960,44638,76422,39165,50645,78706,82001,82739,51187,50129,81192,40934,41391,41837,41827,42777,44731,48517,78359,44602,46736,41759,46966,46979,47137,47140,47632,42963,81063,80663,39252,40024,77919,48115,48144,40101,83448,9224,41721,47613,42161,43700,44452,44743,45147,46809,43975,39156,41130,43633,41084,40948,45859,83820,44593,76565,77293,42454,42635,44596,47634,40965,41513,41819,43467,41585,50357,42247,49786,49852,42445,42440,40917,43028,41264,46653,40269,47128,49795,79610,79623,43257,45489,46530,76819,50804,78500,77181,41510,84097,47411,40146,49522,82108,47141,46542,43216,43794,43519,44009,42784,39704,39155,41238,42236,42339,42601,42731,42809,43073,44965,50433,83432,39528,39639,50123,42367,39550,47615,39735,50748,50769,39680,40646,81356,42240,84256,48531,41344,41657,39736,42603,43627,78468,80863,84585,85581,40560,44931,42797,40145,46735,47631,76667,76731,76750,79683,42766,40976,76666,76782,43071,76702,81468,43539,46120,46464,50284,50302,50379,50426,50448,50456,50470,50476,50500,50544,50566,76692,76892,76918,77059,77347,78325,78331,79115,79993,80105,80987,83361,84615,84780,85177,85544,50983,76674,50749,47614,47637,79846,83962,85551,79160,42525,43622,50458,50465,50479,50617,42823,49149,39763,40149,42239,42368,42531,42580,42628,42631,42707,42724,42734,42850,42870,42966,43086,78705,76960,50272,42582,46817,49113,84697,41438,47347,47143,82036,81896,81924,81984,82069,82070,82199,82366,82425,82441,82473,82017,82221,47612,47636,48871,47119,40141,49975,75479,80764,80867,84378,42762,43337,47154,39853,40161,41244,44670,75745,75763,78091,81598,81637,81771,81910,81960,82406,47012,49523,41380,78763,39576,76516,46854,47421,47420,84083,43245,43316,43317,45919,47004,47083,50770,47149,45465,44916,41654,39401,50718,47144,40802,47135,47150,47349,47392,47394,47178,80896,80933,39994,47151,48866,48888,49358,49982,50997,78765,47620,48544,44440,50155,51197,75153,75185,75202,75224,75228,75233,75287,75293,75302,75310,75318,75325,75334,75342,75345,75358,75369,75417,75420,75473,75489,75514,75523,75542,75556,75558,75566,75571,75577,75598,75604,75640,75641,75718,75773,75785,75790,75826,75836,75873,75962,76001,76048,76056,76096,76102,76131,76185,76203,76241,76296,76318,76351,76517,76562,76584,77051,77375,77390,77392,77437,77457,77492,77505,77551,77569,77596,77602,77734,77741,77743,77746,77754,77768,77795,77829,77847,77849,77856,77857,77860,77875,77884,77889,77906,77909,77929,77937,77942,77964,77998,78000,78020,78026,78028,78044,78066,78068,78074,78077,78079,78082,78086,78102,78119,78335,80948,80966,83342,84164,84211,84469,84563,84984,85173,84525,77418,51198,48872,40990,47059,45977,50985,47416,47719,47133,41885,44868,43172,42508,46146,46151,48892,48555,48760,48073,45590,44057,45361,48214,45928,47650,77255,77264,41748,48748,48889,43120,42639,41399,44362,44939,45028,48887,45719,83174,83334,83497,83610,79651,79652,79897,40163,78412,39106,41977,41978,42401,43160,43296,43328,43806,44079,44080,44081,44082,44130,44713,44945,45055,45213,45254,45255,45257,45337,45345,46569,46571,46729,46731,42402,39296,78330,78392,78678,78889,79857,80506,81327,83482,83693,85272,85386,41623,40299,50835,41482,51026,85539,77277,9274,77475,85459,40095,85512,39652,41552,78246,78260,84961,85452,85569,42230,44592,85150,85403,85491,44562,49549,39456,40984,40685,76404,77064,77067,77102,77103,77108,77148,77338,79261,85213,76381,47171,47648,79696,41036,48604,77362,78595,49554,49558,49811,49812,49992,49993,49994,51430,50169,51201,43183,43405,43485,43774,43808,43953,43965,43966,43988,44215,44979,44981,44523,83865,83951,39265,40915,42100,43786,75612,75701,75765,75702,75626,75642,45020,44842,39991,40991,83415,83485,39338,40796,46485,46488,79401,84625,43313,50962,48227,50437,77229,82566,83797,76035,76167,76175,76187,76197,84667,84687,43704,50891,50911,47436,45962,81316,82950,40174,82003,48755,84683,84606,83300,80419,80141,80202,80422,80206,85023,82082,82156,82040,82057,76101,78207,78378,78596,80103,80137,80464,80747,80858,81117,81120,81169,81175,82049,82068,82120,82136,82194,82216,82832,82839,82853,82875,82909,82917,82941,82944,83183,83261,83283,83817,83894,83928,84216,84594,84939,85359,85446,85553,85578,82983,78565,78681,78871,78936,79023,79080,79147,79247,79272,79641,79940,44287,41992,40746,79140,79046,78915,50173,51178,49114,76324,76341,77163,78591,78608,79454,79470,79594,80264,80483,82497,83209,83866,84873,85268,76336,79694,82867,50715,45503,40381,41697,43433,83641,47167,41624,43229,78049,46007,49097,77166,77307,77313,77323,77332,77343,77364,78150,78365,78526,78539,78862,79260,79406,79850,79974,80172,80260,80435,80511,80927,81026,81594,84593,85038,85105,85395,85461,85528,42412,44246,39865,39679,78790,78497,78511,78905,78944,79135,79231,79360,79468,80173,80658,80714,80745,80801,80923,81364,81420,81441,81479,81491,81893,82107,82564,82751,83028,83081,83132,83137,81442,82689,82836,43810,50964,43523,41362,42369,47153,44505,45718,50001,76213,76226,80092,84249,84363,84768,85138,41343,42942,50170,51161,76243,76347,85228,85301,44235,45511,46289,41878,44376,45105,47026,44924,49243,43895,45533,47437,49208,47185,39289,39447,48174,41647,41746,41776,41949,42263,43000,45183,45250,46619,46630,46675,46897,46941,41016,44751,8758,44423,46171,49175,42863,42961,43661,43665,43770,46646,46654,77355,78495,78561,78861,79056,80484,81628,82149,82205,82894,83705,85524,77337,44260,77329,43722,44359,41272,39257,40467,41811,42347,43112,43327,43541,43836,44325,44384,44483,44832,44919,45025,45292,46230,46233,46292,46293,46298,46307,46309,46508,46511,46520,46618,46621,42348,41269,41118,50222,46662,47859,39386,39515,45609,79238,79273,42054,39172,77210,79927,42523,84517,50646,50892,75445,50526,45861,76965,80461,51292,47181,48216,45363,41087,48554,81399,40498,81664,41907,43460,50230,50912,51359,81300,81534,43551,45435,48141,48558,45992,48560,45339,47174,78437,78745,43509,44790,48556,48762,39397,40318,48878,48552,49817,50002,50167,51155,51320,79001,79041,49823,49555,76256,76311,76372,76552,84752,84763,84773,84775,84794,84803,84825,84947,84964,84973,84982,84990,85002,85014,85030,85054,85070,85077,85089,85107,85117,85123,85135,85137,85153,85245,85266,9118,47647,50287,84076,39213,49543,77090,79826,80782,81007,82733,84837,85040,85081,79336,79519,79276,39522,81994,50446,47429,45804,39719,47440,50952,49259,41817,48075,83370,83443,83529,83684,83308,83337,83289,83271,83262,76712,42847,79971,41509,44795,82011,81626,39669,42680,39797,83394,78357,79054,79333,80020,46378,51432,75754,47169,43178,43409,44867,41295,41852,43244,46926,44251,47805,39731,40292,42536,39340,41237,42181,44643,41395,40612,40975,41669,50836,45739,46482,40595,41989,41234,43227,43329,43461,43752,43807,43993,44633,44706,44783,45019,45061,45137,45210,45315,45317,46663,46667,46670,46804,49550,50358,47645,39662,48753,48885,47176,47642,47655,42730,41848,39637,80744,84168,46210,46304,51205,75168,75169,75182,75187,75204,75232,75238,75241,75254,75274,75275,75288,75336,75338,75353,75399,75421,75527,75580,75606,75840,76062,76158,76173,76362,76414,76418,76462,76596,76628,76975,77167,77391,77451,77493,77522,77545,77550,77554,77572,77578,77580,77592,77615,77669,77678,77683,77691,77706,77711,77755,77778,77782,77785,77787,77793,77820,77823,77842,77883,77946,77959,77975,77995,78001,78056,78070,78106,78124,78142,84137,84209,84285,84484,84689,84722,84955,85063,85066,85067,85088,85100,76373,50175,51434,44754,76602,76612,76474,40633,51045,41538,49115,78916,39422,39588,39589,45606,39530,76014,40729,40578,44605,50440,76812,79751,84375,84827,79395,80360,49319,79284,79561,79759,77036,39688,40649,49260,51433,45234,40062,79808,80079,79630,50647,39207,40650,44784,41780,45314,46335,46401,83207,83246,83250,83284,83321,83476,83336,48751,48759,48873,48876,48893,41825,40761,83853,40253,39259,76047,78329,40869,43869,50936,48119,46872,43510,47426,49430,50858,45727,47187,48163,48550,48557,48749,48761,48882,48884,49822,49995,49996,81348,45733,41028,42091,39102,39348,39906,40096,40231,45805,46316,76694,76704,76735,76835,76934,76971,77110,77258,77276,77287,77294,78219,78549,80216,80528,81141,81283,81360,81443,82050,82828,83115,83600,83716,83726,83972,85087,85350,85366,85439,85448,76841,42099,77297,42749,76682,76786,48891,75517,39670,40734,40503,40866,44660,45278,45570,47161,47183,47432,47644,47646,47654,48196,48551,48757,48879,48880,48883,48890,82720,41045,49541,41245,42522,49808,39282,42144,42145,42615,50700,49930,41329,41942,80485,41183,44149,44402,44406,44426,77231,78303,78344,78347,78653,78857,80424,84527,85537,85598,81517,44934,50362,40686,81682,43945,43947,44076,45181,45581,51063,47927,47931,47901,39405,40453,42889,42442,43251,40154,39238,41530,45366,48217,48222,41100,41519,41841,41967,42209,43765,46855,76103,76701,76705,76708,76723,76724,76738,76759,76765,76771,76814,76842,76843,76857,76871,76875,76880,76902,76949,77033,77034,77041,77054,77247,77251,77256,77265,77278,78173,78550,79295,82016,82972,83027,83094,83138,83349,85547,85583,9335,39337,41250,43276,50218,60078,40112,39811,77142,40814,45770,41970,42316,42913,44033,48211,48295,50003,51435,46310,43191,44902,41577,49556,48881,49204,76691,77138,76526,76948,39816,39446,47172,39454,49173,40434,47162,51429,75766,75768,75782,75787,75794,75824,75846,75865,75868,75877,75890,75958,75976,76000,76013,76022,76036,76044,76046,76061,76089,76099,76136,76165,76172,76209,76219,76240,76260,76337,76389,76546,76549,76566,76645,76746,77152,78198,78247,78343,78345,78406,78606,80038,80517,80617,80983,81894,83360,83516,84061,84114,84119,84192,84261,84556,84561,84619,84626,84790,84938,85119,85326,9315,42970,41305,75821,42649,84043,39119,45295,45586,45608,46419,46815,49919,50548,50389,50396,42421,50166,75898,76554,84436,51439,41698,41702,41835,44534,42235,39699,39304,41056,46204,41317,40535,40386,40724,44280,84099,83992,84030,49573,43580,44804,45264,48808,47188,47435,49652,81155,45495,45852,46152,46457,49764,49814,48549,76423,76432,39823,42231,43935,43971,45415,48224,49552,82835,82945,82995,83010,83107,83270,83388,83503,83537,84051,82890,82813,83581,83442,39596,45059,45663,45795,46076,46123,46531,47179,48213,48220,44525,44729,46556,46558,43265,40243,41794,83058,41630,40008,45367,45762,45931,45944,46147,46251,46552,48226,50171,49816,45954,42084,43526,43536,39162,79773,79843,80515,82626,81187,82603,41896,46742,44636,51046,41059,41093,39431,84848,84860,49246,40579,45851,48223,48561,48077,50384,40158,41708,43389,43390,44535,75284,82105,49952,49372,40059,47168,50581,39808,40431,46009,46705,42932,46191,48338,49821,49991,50172,51177,51437,84115,50703,75150,79645,79793,80048,80490,80582,80586,80623,81152,81307,81457,81527,81581,81701,81812,81853,82513,82545,82638,82725,82843,82914,82986,79697,41911,84032,40609,41922,50340,39854,49643,49879,47673,42874,78285,47635,47643,47651,51021,47182,39503,80477,80516,80557,80766,81463,81633,81649,81715,81760,81836,81993,82032,82093,82137,82155,82246,82510,82812,83091,83558,83597,83652,83723,83799,83864,83874,83901,77283,77291,77308,77319,77324,77357,50702,39716,75584,75862,48221,45625,41481,45626,41671,43104,44753,45501,46216,46916,47163,50265,50333,50351,50382,50388,50392,50405,50406,50424,50498,50524,50589,82542,49225,39287,40773,42475,77421,50648,50859,79799,47915,39496,41594,41768,44458,44855,45329,46052,46584,9239,44971,47047,50613,41226,45075,41718,84062,80561,80950,81150,81338,83706,40457,43499,43238,44077,45176,49824,50000,39990,42993,85292,39123,39575,48209,48563,44950,46332,43089,41402,40324,39479,78306,79084,79144,82383,85495,81927,79038,49998,49999,50160,50161,50162,50164,51154,51426,78542,78573,51229,51149,39500,49060,50860,39827,44429,44897,46575,46589,46593,46596,47037,47038,47045,41293,44862,43632,78465,78480,78632,78842,79259,79905,80939,81249,81717,83305,83483,84983,85402,85541,50256,43129,49820,43514,43515,76244,76284,76734,76752,76799,76861,79838,80866,80873,80879,82591,84630,84678,85229,76747,85280,41515,42256,49420,42469,42756,42667,46172,49192,43360,46862,48219,75341,44683,79802,79869,43208,39344,39345,41449,41796,41927,41929,42393,43240,43248,43399,43518,44240,44459,44651,41451,44639,43503,43768,41503,47180,47177,50808,47439,40556,39597,40033,76401,84193,84466,84477,84529,84532,84546,84548,84588,84603,84778,50649,42644,43247,44693,44600,40038,50274,45472,45902,48108,48218,81305,81050,39570,80855,45512,39249,39630,48146,42006,44607,41869,44409,50352,50219,42211,44360,45060,80499,45057,43194,50965,41580,47428,45544,76129,42916,46738,39140,83890,79142,40671,46266,48189,79547,79695,80068,80151,80196,80259,80427,80487,80494,80563,80653,80674,80681,80848,80934,81032,81054,81075,81085,81351,81557,81573,81663,81736,81776,81796,81944,81996,82010,82047,82104,82122,82135,82152,83288,83375,83733,83877,83982,83633,42951,79211,83306,83762,39445,83225,80613,40803,79512,80979,40799,49156,50413,42865,50884,51004,75469,49238,75963,77097,41421,42672,78859,40351,75295,43867,41961,43473,43887,76132,84681,76126,8770,41560,47425,47438,49763,41423,80130,84313,85441,75565,43684,43042,45552,45963,46020,46228,46256,46305,46446,46448,46449,46452,46644,48608,51438,49365,44822,49815,39362,41964,39848,43267,45567,46327,45838,41310,78130,40989,49164,49540,81579,84771,50790,44403,44825,46674,46701,76246,76617,76653,76911,76955,77011,79949,79994,80061,84149,84234,84540,84632,84694,84724,84808,84914,85102,85252,85256,85276,85406,42081,76275,77266,75198,77977,50366,43429,80434,80391,42950,81713,80660,78603,46371,49813,49262,47434,75464,75891,77069,83915,84235,84244,85462,46607,40164,41572,44150,78903,78933,78951,78962,78985,79030,79067,79100,79141,79163,79222,79255,79279,79341,79351,79399,79633,79715,79811,79890,79956,80345,80369,80588,45030,46813,41468,44821,45054,40208,41872,43852,44468,46606,44718,44716,50444,42778,41496,45062,39292,39277,46762,40244,50623,50628,50633,50635,49397,49186,44067,49564,42146,43280,43743,44792,45868,46851,50004,50165,51427,51428,43009,47649,49261,48362,48562,49427,40875,48894,51047,40853,41806,43764,43797,43849,43955,44652,45422,41143,83938,39872,40670,84037,41810,84108,84068,47427,47433,47441,47640,48200,48215,48565,48566,48758,49392,49818,49874,50913,47173,49559,40198,80953,81182,81321,50650,79545,42783,39821,43747,41753,46428,50750,39527,39775,39992,42259,50276,42882,44594,49116,51048,9172,44923,41480,43278,43299,44026,45789,44284,41550,46117,83617,78160,78237,78265,78410,78436,79680,79738,79827,79904,79932,79995,80098,80124,85497,85507,85533,85546,85565,78255,41492,78405,75788,75791,75803,75820,75822,75828,75833,75854,75871,75916,75925,80495,80595,80650,80739,80839,80850,81561,84288,84300,84304,84311,84326,84333,84345,84359,84373,84379,84387,84396,84415,84421,84429,84437,84463,84492,78426,51431,46655,41693,42652,44278,44912,44913,45298,45302,46432,46784,46785,46930,47089,40185,40083,40537,39147,39891,41857,43186,44355,45640,44700,50407,46060,46113,45818,41163,44303,84047,50876,47166,47639,42101,42759,42774,43602,44519,45127,47860,43600,41396,47854,39577,40303,79365,43060,43669,49242,44541,41340,43207,44430,44456,44747,45463,46435,46745,48830,76277,49730,49752,43121,45196,43081,39667,46187,45428,48097,49408,51361,51314,75299,75495,75510,75512,77470,77564,77680,49393,42935,39525,40974,50914,75575,75605,75688,75738,76236,84487,84750,85240,84126,45543,42224,48754,48895,82276,42480,41261,42158,43830,84059,42801,79587,79632,40677,50411,50502,41107,84066,82823,75476,78263,78797,80428,81016,81668,81730,81735,82158,82210,82880,82932,83222,83611,83665,39591,80101,80119,80125,80128,80136,80146,80198,80245,80255,80319,80353,80381,80388,80423,80471,80656,80680,80780,80825,80843,80902,80975,80992,81099,81131,81136,81180,81514,81654,81747,81882,82081,82363,80106,80498,76553,76589,76668,77022,77049,84792,84801,84878,84881,84915,84940,84967,85058,85060,85136,85140,85143,85278,85286,76963,41274,84757,50583,40382,78556,78766,79298,79347,79688,80598,80659,80779,80859,80875,80910,80940,83187,49551,44252,51095,44407,77095,48588,75596,76662,77817,39923,41651,41845,43969,50592,75492,82463,41850,48569,41661,76390,75989,76015,76118,76177,76234,84181,84203,84215,84222,84258,84393,84590,76012,76079,50192,39950,47308,75252,81837,77484,83462,83601,83734,83758,83801,49577,49580,49634,45999,81863,78275,78312,78609,80286,80290,80761,81384,81513,81575,82394,83078,83842,85434,85580,80349,43413,44272,46156,81408,76339,83918,44144,50794,60086,45602,48602,47938,49933,50006,78860,80219,80225,79126,79040,42743,41874,81560,81687,81733,81797,45192,49593,82512,83127,45830,45863,47231,48264,48342,48641,39507,45882,76447,51049,41605,42638,41436,44097,76300,42752,40046,45491,48270,49269,49589,51148,82891,82920,82948,82997,45265,75314,76155,79121,79637,81566,81925,82114,84226,84248,45384,45396,48272,50183,51181,51282,51283,51354,51440,77209,50182,51180,46727,82815,47681,45372,46006,46050,46165,46261,47586,47853,48259,48578,49394,49426,49434,76191,78682,43910,43973,43983,43987,44836,44885,42017,39451,39373,40509,41406,41557,42682,42688,42699,42816,43003,43056,49608,42067,49197,80763,44109,75850,45539,46490,45681,48230,77863,80299,80318,80440,47674,48920,43780,80558,47467,49363,44100,44471,45170,46288,47320,43492,42111,42822,45291,44023,43043,80556,81874,40323,50651,50721,83803,39129,39438,45116,43333,49586,50716,43432,43434,43874,75994,77513,79740,82144,82571,44370,47444,49985,49578,51441,49585,40369,41351,46294,44396,47189,49837,40246,50654,41646,43636,50436,48812,49205,79159,75223,78010,78089,80735,84995,77811,78071,79598,80555,48188,39356,76707,46910,48582,51270,75615,75978,84400,41836,43001,76163,81741,75557,50861,44517,84464,75563,43340,44813,83319,83165,83269,44347,39388,46943,44486,78208,79235,80962,81203,81237,81269,81292,81402,81556,81596,81688,81920,82154,44254,51421,49253,49497,44332,40929,45914,75622,49496,49546,47237,50146,39636,40237,48658,76395,41336,49521,47219,47215,47221,39606,76883,85338,51342,45197,45593,45970,46039,46077,46173,46974,47097,48096,48708,48730,79110,50159,77468,77983,49574,49969,49970,50025,50137,50138,51293,51323,51324,51368,49831,78097,78069,77969,47576,47193,47194,47196,47198,47201,47203,47458,47573,47574,47578,47676,48212,48904,48905,43888,50886,78931,48750,48877,47685,9218,82048,9210,83481,84574,47638,45935,49518,47170,49514,46876,44375,49153,76974,76993,75909,77373,76665,76798,84342,42244,40791,40954,40955,44824,50208,44544,43547,49939,48080,84580,46082,75798,43792,45950,75436,45940,51242,51243,49841,43335,39856,40871,42351,40399,44147,46376,46481,46500,46582,46587,46629,46635,76529,50193,50194,51103,51211,75177,47690,39533,48943,45917,43565,51255,44204,42940,47742,47701,9236,40197,76333,77032,76567,76288,46948,84953,46768,81875,81855,39441,49629,78514,76639,39301,9193,84843,84840,84842,49633,75698,76558,76768,75930,75967,76105,79722,76120,76135,76138,76152,84365,84388,84418,84433,84455,84742,48644,39529,46012,46232,43103,44171,42365,40812,41230,50212,49666,44434,43563,41218,42038,43110,45204,45305,46227,46229,46241,46877,46878,46879,75635,76016,76278,76297,76312,76331,76453,76461,76856,76905,76935,77042,77050,77212,77215,77224,77242,77260,79039,80238,81416,84171,84705,84737,84804,84850,84872,85261,85337,85358,85536,44944,75639,76898,45307,47459,49663,80076,41166,80712,80163,83710,41447,39365,45657,48370,48654,49403,49750,75477,75539,80676,45051,40045,41266,50864,46126,41242,46466,48661,49833,50021,50020,50024,9176,50014,80430,82660,82965,84571,77303,80794,85430,44771,39624,80482,80542,42727,47293,40227,40828,46652,48909,41523,76323,81371,82594,83260,83605,83739,84371,84696,84704,84958,84996,85001,85010,85018,85029,85036,85051,85052,85069,85097,85109,85114,85121,85129,85147,85505,77030,42891,48906,84302,47447,50242,42564,44400,46816,46818,46957,41026,50068,78180,82557,82580,82604,82699,82748,50450,47328,48088,49024,41437,49682,49700,49693,82576,82659,82702,82742,82877,83089,83109,83133,83197,83453,83470,83602,82634,83098,82687,45802,50443,41358,41700,44576,44632,79202,83468,78524,84364,47227,83941,49714,75286,75294,78095,78096,78126,78135,78141,77084,43173,81536,81494,44165,51366,50112,39494,81878,81909,81806,81951,82056,45413,48407,51319,49694,81509,48809,40404,41189,48586,42356,43346,43347,43348,44564,44557,41194,44998,45113,45327,46460,40398,40419,40433,83978,40905,40682,40928,79618,48319,49967,41540,42474,40195,44748,80064,82719,82738,83487,83524,49961,43557,51259,40010,44606,40634,41082,39543,49335,49929,49754,45492,49934,9123,48589,41490,43143,45242,51065,46810,80640,40949,79411,79716,79477,49748,41452,41743,43364,43386,44040,46739,48553,39324,41108,41782,44286,9133,8760,39895,76408,45613,81450,50111,47802,41003,42626,81195,46860,49088,83278,51228,48713,78970,79713,79741,79848,79877,79922,79989,80040,80110,80510,80553,80686,81666,81707,85031,49944,51389,45456,47797,78945,78976,79078,79096,79097,79112,79130,79148,79265,79290,79308,78866,79232,39944,42542,48463,46808,41184,43369,44415,43760,47800,39894,40762,81755,77625,50110,80955,81240,82537,48459,48468,48716,48570,80354,84045,47372,48841,47323,49635,49602,44703,39953,44782,46981,46982,39937,47199,43381,80807,45927,41533,49777,51289,46264,51336,47395,47396,47400,45580,51300,51301,39458,48745,50118,47191,51406,75973,76629,77073,77384,77543,77693,77704,77750,77812,77931,75795,76150,51356,9186,49512,79204,83666,47810,40012,45990,76283,76286,76343,76470,76481,76505,76591,76657,76663,76855,76881,76921,76958,84368,84380,84389,84459,84462,84468,84635,84644,84666,84690,84720,84723,84774,84784,84791,84800,84806,84822,84835,84882,85255,85273,85304,85314,85321,42516,41177,76265,76268,76313,76320,76346,76355,76369,76376,76378,76402,76419,76428,76436,76438,76442,76444,76490,76498,76544,76547,76555,76557,76579,76600,76647,76654,76671,76690,76700,76727,76763,76778,76816,76822,76830,76831,76877,76910,76914,76987,77123,77179,77188,77197,77244,77330,78149,78214,78218,78225,78244,78407,78428,78607,78642,78662,78698,78773,78779,78796,78802,78803,78812,78838,78984,79150,79179,79310,79416,79498,79571,79650,79661,80551,80894,81031,81072,81100,81396,81516,81905,82045,82067,82173,82174,82187,82322,82358,82371,82434,82628,82822,82947,83067,83131,83158,83346,83420,83532,83589,83612,83622,83650,83751,83778,83849,83903,83912,83916,83993,84116,84360,84511,84513,84555,84578,84633,84655,84682,84721,84729,84754,84788,84813,84833,84852,84864,84865,84874,84912,84919,84932,84941,85006,85044,85076,85080,85128,85142,85160,85184,85186,85217,85219,85221,85222,85233,85241,85250,85281,85309,85349,85467,85518,85534,85575,85597,84081,84642,81995,84501,84531,79629,78370,78430,78490,78576,78690,79043,79064,79270,79603,81640,85480,82113,78559,78541,48599,40030,48266,44054,44926,46539,46969,40278,50409,50455,50491,50579,50474,50722,45508,47204,47678,42043,39178,39407,40019,40461,79464,48900,47454,47468,50096,49263,40967,40454,41342,41609,42049,42050,44249,44681,50435,42894,76413,76445,76504,76615,42551,50249,43906,44101,45893,49008,40733,48203,50220,47455,47190,48082,50226,50237,48205,51357,79657,79829,81848,82895,50188,47661,48233,48601,44055,45536,51297,39293,48916,45338,42104,39743,50915,39664,39686,41556,42118,43052,44788,44997,46235,46344,46375,46379,46384,46523,46685,46904,46905,46907,46909,47006,47011,44974,42377,75381,76090,84199,44062,48265,49266,50819,76773,77507,77511,43344,45750,45753,49868,42600,45119,48083,39413,84074,50231,49268,43658,44895,46677,46702,46959,47072,47073,47079,47080,47081,75446,75497,78912,80673,39644,43976,48148,50655,39805,41626,43226,43308,44614,44734,45080,48243,48571,46068,46150,46237,47164,48508,48875,39663,39480,75964,47668,49872,39449,42060,44503,40638,48579,44728,41431,40898,48912,39988,81315,81361,81533,81618,81648,81725,81731,82311,82391,82579,82610,82654,82744,82952,82963,82974,82988,83162,83215,83240,83315,83457,83720,81355,83366,48587,45269,45839,41761,47032,48584,46782,44553,48267,48775,40074,40201,40497,43312,45888,46059,46247,47042,78722,85589,40475,79345,50449,79515,48087,48147,44768,46070,39841,39117,40751,43653,42618,75757,76114,75759,75990,76218,75966,41254,49209,45379,45710,47228,49270,49272,47462,39151,49825,50704,84212,84217,84218,84225,84230,84233,84240,84243,84251,84253,84264,84266,84269,84271,84276,84279,84280,84284,84286,84296,84297,84306,84307,84310,84314,84317,84319,84325,84330,84332,84334,84344,84346,84350,84352,84409,84428,84431,84448,84479,84480,84481,84489,84589,84228,39414,41849,43214,43724,43734,41840,48273,75980,76188,76367,76406,77248,81204,84158,84377,84453,84751,75924,84512,76154,48262,43585,49834,77159,46478,42747,85191,47234,81196,78300,78308,81267,83294,84950,84316,85389,81181,46107,46628,84093,78458,78509,79014,79313,79319,79390,79727,81954,82024,82327,83949,84730,85227,43168,45782,48248,39329,51348,75180,75206,75209,75356,75367,75378,75392,75402,75419,75425,75490,75508,75513,75543,75559,75576,75579,75589,75624,75644,75987,76144,76193,76352,76634,76636,76652,76951,77012,77435,77438,77446,77622,77668,77707,77709,77774,77865,77954,78023,78239,78362,78427,78449,78503,78525,78548,78553,78620,78777,79328,79724,79867,80351,80457,80684,81077,81087,81328,81389,81482,81489,81505,81593,81667,81684,81745,81756,81814,81885,82084,82141,84143,84450,84526,84584,84617,84925,85000,85035,85104,75200,78041,77524,51347,40424,42036,49576,50015,50190,51220,79881,81616,81660,81758,79863,81671,80964,44113,44116,44777,45280,45282,50916,51138,47679,79676,80054,81045,79252,81046,81024,42026,43496,43498,44021,44179,45801,45803,48911,80316,80805,42228,42033,46301,50012,39538,81335,81572,81754,81961,82161,84405,85560,85599,85554,42979,45485,46648,49587,44010,76827,81635,81716,81744,81785,81801,81610,42925,47206,43494,78016,46398,41066,83545,83724,83737,84012,83603,83447,83502,83595,83721,41574,42871,83560,83861,85347,77301,77371,78176,77222,44035,45368,46083,46138,46198,48228,48229,48497,48590,49381,79798,81451,81692,81765,81834,43111,46984,47672,42827,79325,41262,48592,81428,40233,44677,44727,44736,78810,81406,81656,82332,82400,82921,82967,39471,82623,39163,40099,40211,41017,42024,43292,44198,45742,46343,46787,49481,49483,50400,75176,75178,75306,75880,76817,77358,77537,77539,77568,77653,77675,77726,77740,77815,77886,77923,77949,78045,78118,78133,78147,78423,78440,79414,81721,81926,84170,84550,84857,40986,40550,50656,50198,50197,51311,77552,79647,51442,75721,50018,75162,75172,75181,75190,75201,75205,75211,75221,75222,75257,75272,80095,79997,42750,47911,47922,40064,47448,78369,78404,78424,78447,85425,85500,85564,77503,77530,76466,77408,77501,44806,44837,45161,46714,42405,47233,47926,39125,41728,75737,76543,77315,77326,78391,79239,84198,85353,85520,84591,78372,84470,85274,40405,39732,78764,47680,39164,48241,42119,46022,8782,44363,44365,45429,84065,43616,43396,43403,42061,43414,43441,43478,43555,43776,44191,44201,44205,44210,44439,44875,44886,45140,45195,45216,45244,45430,45763,45786,45841,46014,46017,46025,46028,46029,46184,46319,46331,46430,46497,46541,47222,42512,41790,42120,48090,49267,43624,41779,50281,43968,41372,45261,45263,43462,43481,51310,51325,77982,79903,76928,41722,8790,47896,44200,45916,47902,40354,47669,48235,42812,44970,46605,78935,79153,81413,78521,50239,84673,45629,48238,49373,44787,39505,46255,50023,47683,48245,48268,48576,48769,48923,41695,45066,46690,42203,47195,48251,48767,48907,48917,48921,43680,44765,44237,48236,48580,41916,79500,50862,81280,39394,80885,81577,81851,81418,80834,80386,49271,50253,47457,47375,43558,46559,50251,44478,47667,48193,49359,44164,45829,47461,48915,50930,80865,80738,80832,80488,80696,51445,44883,40925,39433,48250,48255,48595,50176,51237,51443,42956,49579,50264,50189,51234,51235,45618,48260,44186,45537,40704,46892,45369,48234,49826,77559,47671,47214,39196,40783,41847,42370,43865,50241,51005,50179,50709,45461,47211,47213,48237,48573,48574,48577,48585,48765,48768,48924,48926,39270,47463,47226,48908,79777,79999,79555,78882,79300,46219,47142,48919,41985,84092,39168,39886,44412,76043,51372,75195,75210,75280,75292,75304,75355,75363,75383,75389,75394,75400,75504,75515,75666,75774,75804,75825,75885,75946,76018,76031,76054,76119,76137,76181,77517,77562,77613,77701,77710,77744,77749,77756,77775,77807,77809,77833,77855,77864,77870,77879,77887,77903,77917,77930,77948,77958,77973,77991,78009,78017,78033,78054,78055,78117,78122,84335,84398,84410,84423,84432,84434,84435,84445,84465,84502,84535,84542,84558,84572,84575,84604,84639,84732,85185,85248,77528,51370,8762,45961,85013,85045,85444,85465,47224,47456,47677,49276,50752,42343,43190,43670,43673,43938,44228,41321,41181,45646,46407,48603,41691,40804,81883,50907,45373,48261,51210,50178,39760,50181,45912,51179,43901,40168,42770,48239,44563,40248,41508,75636,77996,79074,79129,79409,79434,79448,80602,80833,83193,84374,85047,84557,40702,41663,41959,49530,50087,47139,48774,39773,39919,39930,39941,39952,40320,40341,40342,40488,40490,40723,40725,40848,40849,43165,50504,40340,39537,46572,84110,40169,40678,44250,43035,44479,43664,40196,40563,82588,82722,82884,82938,45764,82384,82697,41589,39177,40961,46580,41393,41486,39351,43626,39799,39192,39499,40209,48240,49827,82889,49590,49568,45699,41712,45053,40413,41284,42500,42599,42911,42973,49553,79796,79937,39897,39263,42852,41679,43443,81309,81323,50893,78891,42676,41330,45664,50074,40529,40752,9137,49569,43484,39223,43790,47202,47670,84035,79540,40474,79531,42607,47622,44641,46338,46357,44873,45332,46337,46405,46406,41316,44865,46874,68646,41265,41773,43375,44959,46367,83172,84291,84968,83682,51091,84224,40525,42293,40466,48150,48896,48910,48132,41665,40329,40356,45117,45121,39357,49588,76850,50865,50995,42117,43436,44860,44861,48149,80288,39650,40781,42307,9110,41280,44628,41792,43837,43845,45007,47008,45757,46623,46997,44648,45624,78062,75879,44270,83135,83164,83118,45979,39873,49172,42647,46108,42952,50532,40390,43651,50942,40966,9324,41809,46067,44143,45724,43397,9390,39975,41157,41067,49159,48091,49273,39330,83345,83454,83514,83648,83243,83307,83554,44462,8766,9178,50186,51213,81830,49158,81835,39392,41459,44308,45103,45207,46291,46356,75235,44772,42457,45599,46142,77488,77490,48771,49835,50005,50007,50010,50017,50177,49594,39807,45331,83839,85374,85334,40732,41412,84016,43994,45496,48271,51335,41298,83700,50692,41456,49567,78798,75995,39219,84978,81976,82095,82110,82752,83241,83475,50016,47212,47225,50816,46273,47232,46752,82524,82620,82511,82337,82567,82532,46437,45703,81529,48572,49584,39478,50011,77826,48901,79602,79726,79818,39201,45270,46811,46812,41110,41187,76261,79613,79756,79907,80009,84893,44012,45437,48263,84401,49188,39578,39749,40171,42129,42406,42448,42533,42858,43098,44348,44351,44437,44511,44623,44686,45462,45889,46214,47465,48258,48593,48929,49571,79442,79687,79771,79806,79807,79851,79870,80026,80030,80108,80109,80154,81809,82026,82063,83557,83655,83659,83735,83871,79745,79886,80178,39463,50085,42023,44413,44662,49274,41216,42549,42226,39820,40300,83925,49277,47657,81638,81698,45042,41877,48770,39647,39349,40800,43428,43451,47077,79916,79539,44394,42196,47451,49366,50275,48922,48931,41823,47450,50286,49279,49174,40813,47660,51362,51365,47663,49582,41162,41174,44488,44746,44941,44952,45290,46501,46600,46703,47218,47460,49581,79526,43419,43454,40034,49592,81288,76837,50297,47452,46129,46133,46177,46297,82528,82933,83031,83043,83806,83003,82456,47666,75611,47662,48600,48252,48257,48269,51162,50184,47229,50244,48092,49278,50713,49875,42997,48596,48756,49280,46716,49157,41167,40570,44627,49139,44656,39167,41726,77061,47235,83286,83430,83458,83520,83599,83975,83621,83263,78230,84916,85557,78279,47297,50963,47443,49221,75921,39751,81886,81987,82097,82587,82753,83350,83703,84671,85189,85458,83093,44307,46418,46581,46903,43249,46189,46753,45780,45980,46377,48249,49560,49562,49563,49570,49583,49684,49828,50246,50260,50277,50278,50280,50282,50283,50290,50291,50292,50294,50295,50298,50304,50322,50324,50330,50332,50334,50336,50339,50345,50354,50356,50359,50360,50370,50371,50394,50395,50415,50420,50421,50422,50428,50447,50454,50485,50494,50496,50499,50534,50540,50565,50584,76509,76524,82073,42799,49282,41142,76527,50326,46963,47210,82145,50837,51402,44051,46058,47230,47445,48903,50308,41638,44305,43387,41741,47659,47684,49656,47466,41159,44947,46369,75748,76572,78215,78310,81088,85068,85230,77806,42254,84528,49409,76590,47665,47812,47813,47814,47815,48153,48187,50751,79369,41276,49829,45817,39879,43777,43779,45393,46656,48591,76380,76386,76815,78024,78387,78668,80433,81820,82013,83404,84503,84515,85345,85431,85470,40406,41219,44616,76480,81171,84620,85296,75943,77630,80377,84972,50723,47205,47656,47658,82402,82423,82662,82723,82873,82934,82367,81212,82058,48232,51164,46281,41106,41200,83588,81968,39693,83662,40730,44373,46192,46660,75165,43017,40441,43560,50753,43065,75450,41770,80310,51317,79552,79475,79462,44379,39303,39963,40510,42048,42195,45484,45572,45819,46573,46671,47834,47846,47848,47851,47856,47858,47948,47959,47964,47980,47993,47999,48003,48012,48024,48025,48027,48032,48036,48040,48044,48047,48247,48605,48776,48927,49557,51231,77282,79832,79928,80340,80606,80631,80642,81109,82883,85509,40855,44481,43824,41145,41032,42190,44279,42429,45001,41688,44794,41659,42097,42428,43766,47973,41318,44996,49176,39825,77016,84668,84688,84700,84802,84903,84999,85095,85098,84885,41180,51446,46308,78851,79403,78929,43578,41893,49566,49591,43814,39938,49832,50894,48772,48898,48899,47223,45600,46890,48254,48902,75693,48486,50754,42655,45149,79107,79823,80033,42409,49561,49565,78281,78286,78340,78659,78704,78718,78743,78770,78785,78788,78827,78864,78926,79104,79124,79152,79233,79275,79340,79385,79418,79778,80055,80162,80235,80300,80315,80317,80380,80758,80770,80792,80915,81126,81225,82051,39420,50009,80224,49836,48244,45806,78707,39421,85387,39574,41676,44213,45374,46215,47449,49281,50320,79474,84799,79988,41299,40561,45058,43334,41061,45021,78583,39671,40346,41802,41928,44371,42504,48914,45016,48567,50013,48256,39425,78830,78592,78486,82324,82341,82375,47216,49572,42910,41021,40212,41001,83576,44613,42817,42051,48107,48151,40893,40355,40856,45173,45377,45877,48274,48276,48606,48777,48778,48932,48933,49052,75175,81890,48609,40425,39551,50191,51099,83347,43448,78572,50223,45383,39584,40514,42877,49838,44612,40415,47013,50445,45701,42143,43410,75289,47469,47471,76316,76358,42758,76292,43427,47200,47236,47687,48934,48935,48936,41424,41547,42416,41520,41803,44338,41065,40788,39648,42947,46183,41534,42070,47577,47579,47580,50331,76454,81257,81296,81319,82353,82625,82734,83741,47470,81568,80909,39188,42156,41938,45221,46370,75157,41999,42001,42077,43531,75971,78305,75961,76104,39212,75915,76775,85372,76844,76492,75215,80287,76725,39531,41475,82312,82401,82461,82910,41005,80525,82321,79009,40167,42185,80265,40279,40280,41090,50513,42467,83773,40129,39929,39852,81524,81417,41729,39677,81825,44131,44151,45101,45722,45731,47238,48607,48779,40712,42905,40696,41231,45974,39607,83341,81528,85435,85416,85460,44904,81792,82126,44053,44075,46246,46362,46697,47245,47386,41204,41760,41826,43554,43556,43730,44990,45222,46185,46965,46967,75643,75654,75660,75664,75646,46368,39901,43338,45869,41861,48937,42552,77175,78736,83868,85225,40865,76986,77003,77180,43282,40249,49595,85239,79761,42478,47879,42483,39174,40659,39372,40041,41710,43289,45773,46880,50966,41097,41801,42835,41285,40736,42234,9311,47243,47826,47827,47828,47829,49015,40794,42807,47477,50342,49284,47217,51008,43225,43297,47063,41430,47866,47872,79554,79564,79714,79723,79732,79754,79852,79855,79862,79880,79898,79909,79919,79953,80066,80155,80236,81146,81154,81188,81246,81308,81391,81570,81738,81815,81858,81871,81899,82080,82090,82125,47864,79556,39843,76322,76328,76342,76350,76354,76365,76370,76379,76384,76394,76398,76416,76420,76421,76338,79127,80237,48945,80073,82091,82177,44114,46884,46947,46951,47476,50353,45225,77325,78389,39269,40627,78817,40147,77289,46894,76039,76040,76128,76473,76507,76829,77508,77516,77797,50201,51236,40511,42297,42965,48154,82343,42151,78739,47688,43255,81167,82919,47474,50364,79578,79581,79659,79929,79952,80096,80169,80195,80204,80212,80213,80222,80240,80250,80254,80279,80312,80357,80397,80426,80547,80559,80603,80703,80785,81096,81270,82857,82970,80187,80262,80292,44640,41717,80634,80682,46467,44898,47001,45887,39781,49360,51100,80920,44851,45279,46436,46440,46443,47028,47029,43463,39435,40584,42687,41758,79068,82469,83963,75475,75480,75487,75549,75703,75468,75463,44119,42829,42907,39518,83390,83561,83808,83869,84036,83798,47244,77971,77620,43707,47249,47689,48946,49839,77533,75895,77535,77666,77525,45453,46243,42157,77772,39523,45908,43598,43599,45138,46891,44917,43382,49963,45900,43899,39113,49117,43567,50918,47084,77328,50699,77585,77697,40445,43639,43640,43946,45272,45507,46422,46557,46760,46761,43083,43904,50195,39132,49233,84106,43171,45513,46361,46454,46634,48278,48613,50203,51101,51214,51215,51279,51280,51309,46991,48612,42115,44368,45743,40177,81473,81467,46975,40336,40338,39311,45228,46898,39813,41701,49601,82515,46638,41633,42170,43018,44584,83971,84090,42155,81444,39251,39408,39549,42426,49206,45380,48283,50657,41365,40220,45720,46329,48282,49378,39122,75616,82731,41600,83087,39846,50372,78029,46118,41427,76084,77950,45579,46962,48280,39110,48076,80538,83100,83845,84010,79012,79090,43279,44834,47025,47027,47247,47248,39258,40230,75691,75762,78804,84552,84577,75551,78835,49109,47241,47472,47479,50375,44333,39951,43209,43220,44530,43355,45033,45022,45111,47839,47954,47957,47977,47987,47991,47992,48002,48016,48026,48029,48037,48046,48050,48055,48058,48061,78153,78194,85548,47943,78885,80439,80883,84094,85410,85522,81672,85473,80327,85376,77769,77780,49783,49959,50698,44985,45365,45346,45550,44655,50063,50202,51216,82874,83152,85043,40242,75191,75208,75219,75230,75249,75259,75260,75270,75303,75344,75364,75428,75447,75465,75519,75541,75581,75894,76076,76141,82362,84121,84196,84263,84341,75370,75188,39651,79066,79053,42083,40334,43877,41479,80083,80194,80373,84883,85352,85363,80016,40200,40476,47090,47094,40471,42030,45688,48284,40162,80135,80142,80152,80159,80167,80179,80180,80207,80215,80232,80251,80256,80268,80276,80284,80296,80301,80304,80309,80323,80331,80352,80374,80429,80443,80670,80711,81018,81019,81040,81281,81464,81565,81673,82064,82065,82085,82115,82390,82493,82495,82929,83747,83763,83788,83807,83829,83838,83852,83886,83899,83908,83919,83924,40958,83904,40620,82077,40621,47240,47692,40923,45645,41484,47246,48938,48939,48942,77130,77155,77241,77367,78336,77137,77659,77662,77667,49283,48177,44461,48198,78453,44207,46045,45993,40214,40533,40872,40879,80168,80185,80188,80244,80267,80302,80616,80704,80787,80815,80917,80946,80996,81003,81101,81179,81365,81422,81604,81775,81887,82106,82211,82345,82420,82535,82578,82684,82685,82770,82775,82854,82915,82951,82992,83070,83119,83123,83147,83419,83495,83523,83759,83615,82716,41455,80543,40935,41808,42270,44015,45454,40202,42300,43310,44016,44020,44126,44195,44197,46734,44194,49842,49843,49844,41205,49840,42089,49627,39605,40839,44781,75603,76568,76578,76624,76648,76803,77363,83955,42795,51262,51232,44496,45684,48285,39608,40238,40240,40319,40430,40541,40652,40726,40728,40883,40884,40885,51006,46603,49675,41613,81048,81086,81122,81153,81217,81326,83500,49603,43691,46805,42550,42282,41352,50200,51169,75409,76878,76938,77199,84886,39216,47691,49598,42723,46157,83422,83465,44490,43096,43370,43922,45126,47929,41113,50967,45123,41441,41112,42041,42213,51007,39777,81824,81873,81921,82007,82088,82119,82123,82226,82301,83669,82044,40684,40377,45545,40479,40827,44389,46333,42424,41153,47480,48279,48610,48611,50386,47478,41129,76586,76661,76680,76606,39806,76660,85234,85235,43737,41071,75264,43203,44946,44663,49189,48281,84159,43832,43117,43263,40660,50974,39560,45896,80189,39914,80601,39267,82255,40526,47473,48940,48944,49103,41300,46061,48386,51351,49287,50027,50028,50029,50031,50032,50033,50034,50035,50036,50037,51102,44659,43153,48093,77415,40436,41115,41301,41677,40250,42341,82171,41535,39442,82191,82426,75192,75220,75594,75775,75780,75841,75852,75856,75858,75861,76088,84140,84165,84182,84188,84195,84265,84282,84426,84443,84457,84543,84640,84677,84739,84819,84807,39793,84518,84520,84524,80400,80690,82686,82837,83930,82903,83032,83062,39475,40589,44464,9116,76409,40252,84648,40617,45730,42933,45421,46164,44441,44956,45141,46798,46799,46918,49575,42616,40076,47483,49288,50397,42501,40373,50817,40662,40393,42135,42909,39956,41944,42126,42261,42640,43285,44438,44597,46506,46627,41680,42990,44570,77758,49845,49850,50044,49605,49897,49997,49664,39501,41241,45389,47481,48159,48318,48960,49613,50408,42175,44955,46248,48296,48616,48793,76595,77472,76840,84785,85263,41375,79175,79186,39689,50850,40938,41867,39158,39274,48948,49087,51096,77234,83989,84100,39535,40792,41979,42149,75506,75758,75789,75809,75926,76146,76310,76357,76361,76382,76397,76463,76482,76514,76525,76534,76539,76571,76603,76638,76689,76792,76865,76887,76896,77008,77013,77026,77035,77081,77120,77192,77238,77320,77336,77353,78352,78402,78417,78444,78663,78670,78726,79611,79628,79794,80013,80727,80812,80878,81172,81299,81449,81459,81608,82159,82483,83233,83505,83640,83850,83891,83911,84148,84163,84173,84205,84461,84551,84599,84691,84702,84781,84820,84824,84845,84853,84863,84875,84921,84935,85055,85124,85174,85200,85209,85232,85243,85283,85341,85417,85477,50225,84734,76391,76649,76756,77043,78258,81904,83609,83929,51050,39877,39436,40247,77979,46104,48313,45680,40565,78269,78284,78384,76192,81209,84315,84789,45399,45442,49364,48963,50130,50792,79473,79488,82168,79447,46079,84167,76325,47693,43505,50365,83254,83732,83396,39281,40102,41092,42517,80791,80846,80888,81023,81111,81124,82269,80790,81228,81290,81542,81773,39862,47490,50866,81135,39864,78302,45820,47251,49616,43288,45996,47033,48290,48633,49626,80931,81059,81330,81358,81380,81398,82478,46382,48615,83860,39898,47485,50419,49290,41489,39383,79902,45097,45654,80826,49618,44174,42010,43785,41855,78030,43006,45086,83273,83376,85393,50725,77632,46730,45358,75348,82964,83478,85211,81093,75161,78987,81233,81247,81388,81766,81901,83667,83709,83441,84029,39609,42390,39694,83508,39599,41202,50401,76108,76123,42796,79243,78946,51109,78320,78454,78878,79288,81544,81621,44724,46576,44987,77751,77689,78997,82297,42056,45525,81705,44183,45821,51105,48964,45620,39532,80467,80505,80521,80677,80720,51132,81781,84232,84564,84661,85169,85170,45151,39126,40257,82927,76713,42609,80705,42788,50920,39218,40251,40416,40417,39134,40370,43366,45573,46792,46794,49604,49625,76945,78839,83702,44567,51081,49210,42395,40951,51051,45049,39809,49289,50605,50625,41355,39101,42352,79366,83186,83201,83228,83296,83559,83774,46473,48322,45597,46424,50726,43471,45044,83449,39926,44504,43571,40028,39738,49118,41673,46096,50659,39222,41308,41526,41859,43970,44277,43931,44386,45431,49131,43603,45621,48291,43791,77200,49621,75458,80959,43362,43365,48626,44624,49617,50368,39828,39601,81370,83439,40442,43136,43140,45716,48289,49241,49890,49891,50047,50048,50075,50077,50078,50660,39148,39602,83856,51381,44744,45395,46380,48300,48334,48784,48949,48953,48955,48958,48625,48094,49298,40574,49628,76869,77281,77300,77309,77311,77331,77335,77366,78152,78311,78323,78355,80200,80411,80451,80493,80534,80584,80651,80695,80730,80797,80837,80870,80907,80960,83638,83783,84994,85216,76874,42698,76879,40153,49611,45694,51107,84629,76904,76925,76966,77019,77055,77127,77131,78373,78628,78717,80594,84260,78271,78276,78693,78737,75887,77104,79494,80810,84353,84530,84404,82514,82585,41868,44293,46986,39903,40819,41325,40259,44110,75620,79760,79836,80143,80218,80809,80823,80847,81029,81068,81231,82055,83658,44708,78694,50755,39880,44328,47019,48320,39887,76291,46861,50661,50727,50756,43884,46317,45656,49854,46964,48305,77321,78598,82772,85204,47835,41407,45755,50451,50258,45689,48321,48353,48614,48781,48957,42924,50950,39721,47493,50863,49291,46182,46793,46790,39617,81470,82849,82852,40907,81472,46433,48311,48787,48962,48620,48065,43295,45937,39784,41494,41940,41943,43358,43803,43838,45153,45518,45521,46314,46354,46355,48597,75158,75261,75339,75347,75352,75368,75415,75442,75443,75493,75720,76026,76070,81724,84208,49211,80817,82916,83015,82782,79536,79642,80378,80678,81932,41089,80624,79795,48095,51053,40115,81066,47258,47694,83898,44850,44853,44929,44936,40006,40152,40841,46285,47098,51358,81780,82546,82592,82619,82670,83148,83189,83219,83251,83304,83421,83431,83525,83569,83699,83786,83875,83942,83948,83985,84000,42243,83944,48156,39539,42332,43059,44667,45156,45209,39285,46122,83054,50662,42130,40573,42494,45768,45814,84046,42346,39166,39625,80635,80697,41607,49120,49653,49542,42410,43099,42423,9287,40913,40492,39288,47252,47704,75461,45381,45706,45811,45955,46021,46295,46980,48288,48298,48314,48317,48391,48534,48568,48581,48624,48627,48651,48704,48725,49856,77784,77825,83548,50457,44548,48628,42893,49177,40264,50987,49853,41236,42484,43202,43728,44177,44180,44276,44991,76065,76034,48618,45972,48790,44833,45714,46345,50414,50481,49212,50261,42846,45394,48299,48301,48323,48326,48621,48623,48688,48785,50045,50205,51104,48970,39598,78647,78778,82283,78558,79139,78587,78614,78645,78680,78700,78714,78868,78908,78955,79133,79145,79166,79213,79251,79269,79283,79293,79324,79326,79343,79355,79377,79460,79569,79721,79746,79812,79976,80042,80085,80093,80123,80160,80344,80456,80938,81114,81139,81165,81578,81697,81915,78590,78600,78631,43406,78316,41149,39103,82503,83023,83156,83160,83200,83303,82940,50285,83322,47873,47867,47888,47881,83302,40494,48791,48954,48971,49848,50042,80829,41579,50895,39918,43870,78235,78563,78870,79665,80628,80740,82282,42079,43459,39723,49294,39832,43167,44025,45886,46527,47253,47264,51290,51291,51345,81352,81378,81411,81462,81576,84951,45807,81511,43594,42310,44595,41864,41122,40132,42164,47266,49292,43720,45723,41227,43738,43911,44625,43912,41288,41487,47261,47698,76532,41462,40970,39581,39850,83686,39740,40616,39379,43675,43719,42944,43274,43452,43801,44160,44161,44162,44167,44170,44172,44173,44176,44810,45076,45232,45243,45312,45313,46442,46475,46708,46834,46858,46859,46977,75307,75496,75684,75829,76006,76117,76184,76195,76250,76285,76329,76374,76429,76718,76762,76795,76851,77128,77206,77317,77361,77374,77378,77383,77399,77400,77404,77411,77436,77445,77456,77476,77478,77480,77497,77499,77518,77536,77546,77556,77563,77574,77575,77617,77638,77656,77661,77679,77694,77699,77731,77737,77739,77748,77761,77783,77824,77834,77836,77852,77858,77876,77894,77939,77953,77961,77972,77994,78050,78100,77905,75806,77688,39878,83884,41789,42201,43739,43928,40165,40438,39486,84351,43897,43886,44642,50766,41165,44048,44107,45604,75414,47262,43839,81870,39706,40178,49293,75970,46090,39272,45922,39945,40807,41259,43735,44680,76060,75892,75357,41767,44739,49609,43236,43628,44383,50932,43831,48129,45798,42751,45135,43637,48979,85499,51203,51204,76542,51207,42643,44410,45382,48292,9219,44800,46091,46820,46838,46839,75979,50041,75380,75408,75398,43468,45700,77121,80199,42569,9257,75466,39215,48287,43125,45087,45300,47061,47064,50771,39393,40255,9131,42679,43374,41478,75834,75678,42992,47250,47703,49150,46633,39526,39933,42222,78195,42838,47184,76515,76519,42350,82151,41222,39278,39352,43487,44290,45115,47009,81098,80609,40741,45988,48303,48310,50132,50204,51106,75901,78491,84027,85194,79961,39910,40029,46819,75625,75957,76200,41025,79495,80395,85365,79817,44675,47265,42602,41457,44017,45385,48411,48682,51339,83351,49379,47488,50431,49295,82059,82235,82370,82453,49847,49606,49610,49622,49623,49846,76794,76796,77044,77076,77170,78472,79027,43189,43411,43645,45136,45211,45284,46387,46636,46637,46754,46755,46757,46867,46868,43326,43415,39604,39465,83511,83624,83934,83996,39443,39444,40234,41625,41956,43271,43826,44134,44476,44863,45096,45693,45915,45975,46208,58911,75397,75406,45874,43822,9456,41224,41750,46287,50742,41232,45420,50772,77389,42802,49615,43155,43157,44619,46946,48997,44815,40333,75638,76069,81728,82127,82435,82533,82627,82673,83791,84738,85604,39995,47259,48127,48155,48182,49245,78092,39977,50939,40920,76264,75686,76455,41258,75586,75599,75983,76585,76621,77056,77058,77146,77279,78529,78560,78581,78602,78619,78625,79048,79062,79128,79504,79529,79742,80372,80661,80796,80835,80844,81033,81739,81942,82207,82410,82416,82449,83069,83371,83750,84142,84900,84924,84952,84959,85046,85048,85262,85265,85277,85307,85603,78968,75652,50695,45956,39826,41405,45565,48622,48780,48950,48961,48965,48304,42760,49213,50367,45277,48294,83927,85550,46403,50838,43985,45612,50921,48057,79306,79428,83038,84038,85549,79172,81483,44869,45622,46186,43455,44668,82335,82422,82468,82611,82649,82764,82924,83343,83369,83389,83486,83574,83674,83714,83743,83796,83819,83855,41422,43449,80752,80795,83844,83530,39290,81097,81106,81241,81342,81382,81477,81642,81679,82827,82923,81080,39225,40426,48293,41117,41599,42794,77010,77193,77221,77233,77245,77038,77115,50314,80887,80778,78065,43119,76091,76125,76147,76166,76176,76183,76202,76216,76235,84219,84355,84391,84402,84403,84446,84447,84454,84475,84486,84490,84541,84553,84579,84609,84624,84634,84650,85161,85426,85586,84537,44302,76186,84399,81157,81236,81265,81287,81332,81366,81466,81695,81734,81813,81958,82033,83011,49045,40286,84717,48968,45735,48523,49614,49849,75732,80806,81216,47695,46005,49860,49927,50038,50039,82274,82288,82319,82356,82421,82437,84846,43284,44300,82251,43198,42855,76961,82250,82265,82487,76681,42849,44866,78425,40009,49620,76556,76597,76863,77009,82293,82314,82378,82484,83922,45444,39452,40897,43341,44498,39691,40536,47131,48686,39746,41667,45412,47652,76228,48098,49285,43385,42890,39661,41190,43652,44262,44263,44527,44537,44343,39971,45639,78398,45481,48312,78257,78507,39672,43363,47492,42404,85325,83116,45388,46365,47484,48309,48795,48966,48967,50442,49296,45898,51202,79789,50210,42819,47706,49299,79781,79825,80015,80082,47255,44492,41686,39830,40545,44093,45591,46336,47482,48951,50453,76229,79654,82256,82925,83358,83414,83781,43516,76224,50883,83740,82148,82111,49607,45188,48315,45296,45299,45387,46797,41926,40539,79856,79891,79936,47487,50464,46003,41460,49858,50040,47702,49450,49458,49474,49857,49859,50043,79915,82231,82247,82278,82326,84596,50046,41164,50163,50206,48969,49851,50793,41732,47256,45825,78621,75632,75870,46031,47699,48307,48783,48956,48959,75968,78633,79162,79168,79193,80370,80410,39983,41073,41044,42488,46279,40972,40679,42528,43442,44568,40508,46032,49631,41206,47696,49612,51416,46487,78847,83660,40632,42062,79963,47489,50475,49300,42597,42789,41403,49286,49686,41214,42298,44609,41563,80129,82663,82679,82713,82798,82840,82898,82960,41124,43712,46900,47263,47705,84231,45308,45732,46086,47254,47257,47389,47491,47700,48099,48158,48792,48886,48947,49301,49302,49303,50764,51264,45523,45355,47269,47272,48327,48972,48973,50051,43530,41077,39228,40717,40718,40720,46112,49244,46640,41408,39717,48006,85511,43872,79828,41980,41114,43650,44321,50452,39722,44755,47712,47268,47713,40376,39957,43864,42557,41921,44682,45448,46686,48207,50818,47900,40190,44091,40816,42713,39534,45857,46549,43124,48330,47898,75388,84356,84357,84358,75377,41655,44704,47031,42075,43804,44122,84664,85148,85159,76889,76521,84570,41709,40785,47271,39194,39295,45891,41257,78238,78229,78278,42537,40005,42268,41464,82164,79590,44793,42677,43750,41101,45967,46179,48333,50049,40204,46399,40552,41160,43351,46915,48797,85237,85269,85270,85556,76531,48325,76353,44428,45636,40586,42955,51079,41541,48635,39358,40263,40266,50990,51031,44071,46765,78301,50263,50423,50514,79766,51054,46993,46441,41983,41984,42233,43175,43843,43844,43952,43963,44208,44369,44608,46712,46718,41565,44555,41566,42792,41378,41014,40016,83634,83646,41246,48975,46953,42354,42399,42490,42570,42589,42648,42720,42834,43025,43067,80853,85101,85118,9263,43495,41765,41769,41880,43233,43871,43954,43957,44132,44135,44828,44874,45104,45182,45218,45252,46931,46933,46935,46937,43908,44419,49142,42004,40968,42382,40784,44470,49119,79728,79894,81238,82338,85331,85360,85487,45418,40302,41463,42800,42964,45392,48324,49374,49395,49396,49425,49443,49447,49454,49471,49473,49476,49477,49478,49482,49487,49492,46507,46238,43034,41103,43961,46190,46553,46925,47710,60088,49370,83670,40908,76121,39127,40345,40598,39634,39504,40217,48332,42018,45623,49140,39124,42413,85423,85380,48328,39306,41105,41798,43789,43793,78371,79528,80384,81794,84449,84560,85079,80572,78273,50387,42140,44472,41146,49406,46835,44707,44475,46870,48764,45687,76801,79061,83594,83976,84004,84005,84494,42805,51392,39837,47914,39410,43221,46550,46771,46772,46774,46778,46781,46803,46987,75755,75760,75801,76049,76064,76083,76140,84545,51271,44657,49226,40651,76189,9294,41390,75900,75977,84867,50827,44878,45653,45193,44382,42470,42187,40507,48634,39322,50977,51019,51222,51223,51284,51286,51403,51414,84050,39339,39876,40281,82318,82300,40407,48629,47878,41531,40857,43881,44801,50601,50624,43850,41690,50922,78108,41948,41951,42002,44551,44622,42265,76289,48794,41267,46821,39415,41116,43092,43148,39509,50434,50549,39656,48632,41029,47709,40888,42378,47002,46924,50053,51111,39229,40624,44588,42726,39482,44960,46895,49475,50306,45229,45478,46773,40703,40877,45920,75151,77006,77927,41079,40787,41833,46078,40876,42353,42337,43350,44229,42848,41506,41844,41846,45758,78895,43239,44029,39965,42972,43122,41912,39333,45125,40063,43618,40464,42791,44650,41597,39915,43342,9197,39175,41889,41910,42103,42565,43868,45526,47048,43372,43379,50235,46740,40065,80597,40306,50896,41518,41783,41784,44460,44957,50216,41401,50710,80751,45540,50663,42526,40015,42888,81720,8768,43100,41904,82380,49232,47495,50741,49304,84060,51256,51425,84372,84582,85418,50429,48631,43823,39146,43314,44152,44973,45006,45064,44482,42335,49195,42772,46857,45031,41453,50588,50604,50606,50627,50632,50634,50636,50637,60087,79998,80181,80473,80688,80941,80968,81512,81582,81652,81948,82590,82845,82930,83121,80086,80721,40176,40210,39548,78018,75186,43948,40085,40347,46564,46912,44928,48329,45548,81585,47494,47711,50050,49309,47934,43426,41332,48669,48976,50279,40687,42009,43916,44880,45079,45322,46665,46700,47060,9344,44518,81559,81121,41435,75876,43986,75726,40608,41902,42168,47270,49861,43228,50666,78505,78527,78720,78853,78977,79582,79627,79668,79879,80029,80165,80191,80210,80355,80385,80533,80541,80587,80985,81103,81186,81430,81659,81872,82025,82030,82043,82387,83582,85085,76908,42427,84985,46856,75744,75914,75912,78282,78283,78291,78270,49227,45895,47707,45571,49632,9229,84829,84744,84508,45556,40261,9234,42276,51009,42280,44603,41253,41411,39133,40224,40721,82984,82819,42719,46649,50867,82376,83618,83789,39519,80432,80446,80548,81263,81271,81670,39546,41068,85493,47714,43937,43940,39860,50427,43524,40495,39585,44963,47058,48275,42497,43846,44993,46758,75335,77663,77670,77700,78002,78025,78034,78099,78113,78121,85099,41814,40969,41030,41699,39307,50667,40362,40307,85004,42245,45528,77839,39931,42499,42606,39309,46135,46194,47715,45842,48564,45695,78266,80069,81488,81826,81908,81914,81982,82008,82034,82160,82178,82208,82224,82344,82755,83001,83024,83324,83496,83515,83843,83952,84877,48341,85220,42329,78686,78716,41049,41716,41771,44845,45091,45220,46684,75687,76478,76828,78809,79296,79339,79421,81508,82128,82197,82743,83034,83857,85210,39618,84631,40549,41445,41123,44361,45083,45084,45093,45319,45398,45761,46098,46386,48335,51303,75360,50923,48638,82829,45534,48336,48642,40023,40330,47769,50712,45704,45717,42301,42309,43816,48974,41488,50055,49214,45652,78909,42148,46099,46103,46545,46850,47273,47585,43161,50898,46047,46053,46224,46383,47120,75906,75951,76385,77449,77643,83570,76263,45976,47587,47591,46913,43606,42262,46902,39727,84221,47847,78537,46220,43757,48637,40715,45981,40021,39205,43709,47500,50841,85538,49265,49306,45438,41389,49862,49640,44572,49151,50868,84082,47496,39964,46394,39645,49644,78540,78689,78713,51288,42921,49864,82607,82797,82900,82935,82998,83009,83840,84080,44145,47278,43828,50668,42102,39682,44175,48981,50689,42815,84040,40591,79451,50724,42573,39810,40446,78328,78452,80626,80630,78396,39755,39922,46750,40020,43177,85322,42266,49305,40663,39959,41075,44043,44068,39547,41052,80689,47279,48978,78416,79178,79810,51097,51113,41962,41968,42165,42312,42832,42862,44719,47043,48337,43610,46453,44808,48343,46533,47082,41144,50240,40926,41080,43517,43520,43548,44011,44445,44881,45171,46567,44444,47276,76807,76613,45810,51315,39361,41991,42538,43552,50509,50515,50590,50594,50596,50600,40892,51023,82330,77288,78523,83847,83863,49645,48157,39244,50839,48977,48192,48639,48136,48100,48983,78299,45968,46919,48242,48340,48636,48737,49636,49866,42066,47498,82147,78445,49639,48277,50669,82460,49865,49867,50054,45401,48345,50572,50773,47274,75683,75728,75988,76127,77093,75545,75750,47716,39542,48980,50056,47499,49307,50486,41382,39552,49646,51250,50869,39182,39346,40241,46562,46707,48640,49314,49642,41154,43825,43892,44030,80295,45816,44381,41824,45754,48344,39498,43163,44356,49638,83042,9170,40754,41813,42105,43114,43126,81476,81344,82799,80620,40440,83704,42106,81919,42515,44288,40144,9205,50745,46617,44231,41527,44508,75972,8778,81949,81597,81493,79774,79840,79873,79945,79991,80002,80008,80012,80019,80024,80031,80051,81333,81646,81674,81798,81860,81889,79893,81278,81699,81840,45341,39450,41324,39540,44377,45752,44644,43620,48346,39789,81435,39240,84442,50870,39360,46137,81119,81144,81239,81746,42482,81429,82035,41591,84424,39143,39678,45281,47007,76030,41569,76196,9320,41816,48347,40713,51010,39268,83914,82193,83672,45759,48643,83701,49630,49641,78978,9195,39695,39354,40025,40159,75488,75530,75585,75592,44846,44840,83810,40665,39562,39189,75653,80786,80856,81036,81567,81791,45450,46351,75800,75808,75648,75659,75694,75756,43464,80877,43893,79507,79573,79389,47717,45067,46934,43094,46218,41544,42197,76894,9252,41042,41670,50338,40113,43323,44219,75449,79176,81530,81972,85543,75771,75478,39457,45854,44395,40332,42642,43562,43159,78801,41603,39564,39276,40559,40980,39199,43078,40543,42479,41483,44785,47066,81719,81703,40294,50774,41875,40325,40795,42641,44138,44139,44140,46462,46471,46477,40793,41706,42708,44529,39536,41179,40666,39355,79876,80999,81800,81868,81906,79901,80701,80474,80692,81714,39363,39411,76295,48160,41186,85432,39685,49662,78883,44560,49001,39384,39464,45936,45494,48377,77002,77135,77340,76852,83816,47296,46495,49671,51346,49673,39831,40031,46626,48994,49672,78217,78361,82408,82745,85409,85506,49002,44654,76267,41387,47747,47280,40688,85424,40262,82868,82973,82712,83374,83725,51108,51224,84298,84343,84382,84476,84440,43392,42272,46153,39620,41642,41937,43260,44078,45403,45404,45574,45576,45583,46840,50376,50404,50412,50417,80700,83767,41514,50820,51074,39592,42398,39973,39629,47281,81142,75424,75819,75832,75838,75859,75864,75869,75882,76179,76201,78988,80564,82776,83936,84303,84323,84384,84458,84713,75835,75522,75412,81034,81073,81112,84411,50070,43388,44690,44691,44692,44694,41034,79423,76777,76737,42869,40486,47511,84395,40131,41418,42011,43309,40213,50842,44185,77454,44769,50510,50840,51367,43493,48802,43180,44098,44779,45102,45157,45185,45206,45233,45266,45268,45402,45464,45475,45497,46342,48348,75351,80097,80107,84769,41628,48655,75956,42007,50067,84975,77249,48204,42629,49422,46474,42998,47940,47970,83157,84091,83998,49264,49275,40448,40743,40744,40899,40900,42229,42381,42385,42577,42636,42674,42702,42728,42803,42840,42904,42939,43027,43047,43053,43054,43093,9423,42826,44514,41148,39406,80845,81229,81312,81350,81433,81592,81702,81749,81911,82467,83143,83213,40780,81507,81207,50968,78376,80578,81690,85588,42258,40981,43277,44969,39184,39993,40768,51150,51251,51327,80502,40117,50729,76319,76541,76699,76722,76780,47502,40740,41636,42380,43231,75965,76004,76107,76472,76620,76659,76733,76755,76766,84406,84499,85009,85224,85267,85288,42843,42782,49667,41290,46390,42623,39972,50758,45873,82811,84067,47501,47505,80540,80722,81284,81304,81375,81718,82787,81331,51055,76931,83199,83184,42511,50777,82765,39312,40312,42960,46140,50248,50255,50262,50270,50293,50301,50309,50318,50323,50346,50462,50550,50595,50593,49414,39641,45659,45985,46121,46366,50611,48376,76417,76204,76215,76223,84111,84117,84124,84153,43241,49674,43917,40851,45005,49022,49228,75899,75552,49660,84817,39640,39185,42555,48375,42053,80784,80585,50071,42152,41337,39782,8756,44141,44196,48253,48361,50670,48372,49881,42437,45796,42521,42496,39510,80227,42545,42860,75258,75981,76041,76086,76497,76604,79134,81650,84259,84277,84281,84312,84338,84417,84608,84658,84711,84725,84783,84911,84930,85050,75184,83410,75568,75725,75227,51130,49877,42493,49676,75433,81325,82157,82169,82188,82195,47302,49741,39791,48989,44453,49665,77047,78422,78644,78781,78911,79173,79187,79241,79250,79574,79660,81025,81500,81859,85351,49121,49887,50061,42167,45734,48379,81458,39382,78938,76996,79258,78504,78918,79302,45364,44848,43591,41704,40288,41440,41553,42219,42295,42811,42818,42879,43090,43162,9429,43088,44313,49178,39377,45904,40447,47305,75535,43559,42012,44510,42379,9519,40348,39187,39715,9157,40919,40930,41493,41931,42433,50439,40026,39692,41211,42169,43197,43444,43654,43656,44942,48369,48350,44125,45676,81937,39794,42274,43748,40822,40142,47290,49661,40140,47509,50497,49310,47282,47737,47291,47746,48987,47506,51344,39949,50577,44733,47514,49311,39700,41866,44411,45958,9138,47109,47913,47924,47928,75749,47899,75242,44539,46719,42562,82773,82540,43215,43538,43049,41858,41965,42199,43303,43440,43608,43990,44697,45011,45930,45941,45986,41656,75567,82678,77031,39214,48999,49179,40667,41635,51343,76743,39396,85591,47750,46044,51196,49030,40350,45109,45120,45848,46018,46084,46865,48364,48453,44829,40775,47735,47743,46583,75373,41666,42438,39912,44637,78547,49888,50060,76024,76053,76113,76248,80694,81082,81105,81562,82061,82411,82429,83352,84628,84670,84684,84693,84759,49654,41525,77796,9113,42507,39209,39511,39512,40449,40846,43200,43422,47319,47740,48657,49619,78654,79954,81465,83259,83613,83694,83711,83999,85344,42319,83770,42434,39801,42554,79248,83867,43550,75481,84708,80905,42220,79858,80969,83873,83945,44239,79889,41225,79797,82556,49885,50062,50064,47732,79824,84699,84712,42447,50296,82838,83237,84980,85073,82882,76411,85024,85271,85180,45910,51338,46402,48086,40767,41971,42124,42357,42436,43772,44227,44566,46833,82688,39180,42249,76142,84207,8792,76122,41888,47739,40551,75802,83290,84727,85381,83198,40418,44086,45677,42450,39917,45642,45938,45942,45957,45966,46023,48351,48359,48371,41747,45934,45921,45751,45522,45925,44882,48048,44543,42901,49152,49677,83668,47751,50899,50889,40604,48863,50671,45078,49882,49873,49855,48161,42604,84856,50069,42242,42471,42697,44214,45074,45691,45905,76021,81353,81461,82534,82550,43138,41448,39378,41074,78250,78350,79483,81394,85383,85397,85449,51353,50925,39253,44115,45628,76761,76916,76930,76979,76990,77037,77052,77074,77092,77114,77272,79770,79821,79892,80472,81005,81953,83050,83471,84194,85324,85369,76619,76610,76670,75740,81480,81891,47295,80401,48367,41376,41891,41895,41914,44446,44449,47731,49668,75733,45705,47749,39368,43281,49935,81548,85438,75908,39911,83509,42830,44752,80023,40283,49145,39698,47304,44202,45697,46796,48800,49011,44975,51115,81700,81641,39579,39730,44083,45034,45065,46837,47949,47983,47984,48013,79170,82028,51078,79087,42739,9380,47942,43421,76688,77116,46852,46875,40891,42275,81969,79692,79580,39315,40061,78855,78906,80403,80757,82351,78893,82440,39750,83492,83707,82381,45178,43331,45378,46166,48801,48984,48988,48998,49017,49018,49021,47720,47721,47294,41169,50672,48982,47718,45611,41740,45708,47186,39412,50673,49180,49785,49971,49658,43102,43101,40837,42096,43307,45913,46054,48368,48990,49669,49670,49883,50065,50066,78151,85443,41981,48357,82816,77310,51119,44255,80261,47003,41161,85328,45678,44531,47593,47594,47595,47596,48162,47599,42462,44435,82071,48102,43958,42141,45564,39748,39759,50924,39220,39242,40599,40601,82277,81151,49878,47726,47744,47745,40044,79609,47092,40047,47298,39758,39369,42150,45685,48805,81658,40731,45982,46551,48648,83299,83940,40180,46554,44856,49230,47292,47728,47734,47865,48803,49010,44050,48650,50744,76227,76238,84134,84160,83313,81390,79317,81492,81729,81764,83163,83477,81675,81393,46932,40468,47303,48131,48165,49004,43696,41256,77655,39255,40235,46163,47364,48659,49005,40585,43325,43456,78368,78390,78571,78577,78671,78701,78884,78897,81248,81967,83331,83573,78485,40228,41341,45651,47837,47990,48004,48007,48014,48017,48023,48038,48059,48062,48103,78178,85041,45486,8794,76282,41559,39426,46748,77134,77235,77349,85587,41608,40714,47510,48084,48164,49007,50507,50797,75974,75986,78315,78377,78775,78960,78980,79253,79426,79457,80190,80325,80346,80441,81293,81918,82198,82275,82308,82346,82407,82474,82489,82522,82569,82646,82736,82737,82788,82826,83629,83681,83695,84500,44378,44618,41434,42844,8776,49234,82233,79847,50843,41212,44398,85399,41639,81452,51056,49312,50243,40512,45405,45949,48374,50674,39271,42453,75505,40100,41505,43332,43980,43996,45130,45142,45226,45259,45309,45519,46509,43783,41504,78660,79344,79420,44484,49009,47831,40134,42132,42136,45371,46311,46312,46625,44740,44495,46996,47036,45063,40979,41191,42121,41354,41900,49678,41871,51141,40435,40818,49196,80094,81242,85419,85531,46504,41954,44295,47945,47955,47960,47968,47979,47988,47994,47996,47998,48009,48015,48033,48034,48039,48045,48051,48054,48060,49003,81676,43926,40056,50871,45747,48360,81339,81404,81448,81622,81845,82304,82350,82711,82860,81685,41012,50759,82037,48104,49313,42015,49006,51152,51163,51174,50872,78696,78710,42403,9524,48197,49679,50495,80063,46358,51085,39417,49486,40309,49236,39871,40357,40245,40254,40258,40420,40422,40423,40455,41038,41039,42194,43033,43330,44425,44448,44908,45167,45241,45330,45342,45353,46257,80750,83794,42008,43032,44940,42019,76601,49870,49871,43829,43907,50926,75265,40924,41629,41881,42694,43211,44036,44070,44072,44148,44314,44721,45008,45146,45240,45251,45509,46262,46263,47020,47021,47022,47095,48365,77512,78039,78094,9393,49220,42859,75404,46124,80965,76622,49099,40215,80645,51011,44760,75902,48647,39516,77822,77830,39149,77934,77999,78060,77938,78697,78758,78768,78784,79119,79242,79359,79374,79380,79410,79427,79429,79440,79449,79511,79533,79559,79622,79792,80035,80059,80090,80139,80164,80171,50131,48656,43690,39145,40127,44364,43412,50229,47532,45004,47513,50830,42670,81861,47289,42934,47504,43891,81620,44658,46863,45756,81336,81243,44577,80895,40700,48352,75633,75677,75889,75975,75878,51057,75847,75866,43876,48378,42308,45682,41950,43192,43256,43268,44105,45150,45152,45155,45748,42487,41471,39845,47738,47283,47284,47285,47722,47723,47724,47725,47727,47729,48796,48804,48991,48992,49014,49016,47287,47288,42575,41419,47300,49105,84034,41379,49728,41255,44679,43511,46927,42000,47894,48993,48995,76520,76530,76536,76587,76626,76627,76650,76675,76909,77079,49869,49884,48105,43075,50927,82536,82624,82846,82863,82865,82498,82565,82841,82481,76348,81432,41366,42299,42700,50873,82298,47299,45406,46071,46791,45473,39590,78673,42417,85396,46057,49880,39703,46978,77048,45792,46744,84042,44344,39128,42291,40366,42338,42736,49020,39429,51058,40344,40544,42094,44087,48106,48373,49104,78185,78584,78982,79049,79098,79441,79882,80192,80421,81158,82427,82516,85411,40374,40901,40902,41172,41795,41793,50288,40941,40745,39753,49137,44915,39834,42938,81272,78245,78457,40003,41727,81379,79433,39472,40540,40352,40797,39754,39586,40360,39317,42435,39432,44350,49367,42193,41139,41575,44241,83715,41498,49143,80753,80734,39780,40403,44323,42328,49651,50928,45673,43311,47431,41346,50629,40282,40776,40777,40778,41924,41960,43108,50523,84719,84652,42581,39370,42953,41364,84598,39279,44513,42414,84033,78167,40375,49215,51032,46132,47111,44932,75922,49012,47503,78188,43319,41631,41966,44900,44711,77943,77947,49655,49659,79750,40358,40359,45189,45190,46234,42303,41645,43582,85072,41601,50776,44487,42748,43270,39709,44318,43131,40531,42876,49647,39882,45546,39714,42996,84070,45605,47123,48366,48646,83614,83399,43030,83464,83484,83493,83535,83632,83691,83990,83356,80882,40379,40151,39387,40491,42495,44443,48349,48354,48355,51185,51249,48167,48079,44269,39728,82871,41467,41081,50508,43272,42292,42706,39785,77080,85199,41394,47507,47512,47748,42459,39280,43820,44190,45864,48363,48645,39925,44289,44723,46806,41370,76592,40175,76703,76717,76791,76845,77183,79384,81772,85510,78876,79055,78800,45884,48166,48356,48649,48798,50072,48536,49013,43522,82329,82403,82454,82464,82477,82492,82509,82517,82575,82583,82637,82768,82879,82966,82976,82547,82320,81956,82347,81768,81586,82202,82099,80514,80549,40513,80492,80412,42271,39907,41126,50236,43619,47753,39835,44978,45285,45304,45483,45634,46577,47071,48358,51278,50969,39814,42212,9432,83293,40067,83398,46612,43170,84460,84505,84605,84828,84928,49657,83498,45471,46723,51230,51209,77928,44582,84743,42422,41925,46037,75650,42107,9282,44209,84637,84087,78695,79772,79805,83088,83099,83125,83170,83212,83314,83368,83411,83460,83461,83512,83821,83906,83909,83926,84731,83071,83330,83235,46631,47736,40183,81770,81818,81917,82969,83549,79417,40265,42741,42027,46767,49315,84305,82728,41426,49649,80775,82257,78226,78379,79508,80329,81999,82014,85455,41742,46843,42317,78332,39232,40219,41641,41672,41860,42277,42302,42510,42769,42821,42943,43077,43919,43930,45118,46777,39230,41450,40222,50597,45002,41537,39998,45326,78730,80145,80337,80375,80743,83895,83897,48996,41731,44096,42153,41098,44451,82018,82092,82413,51219,44282,47068,77096,78894,85323,75430,42683,75597,49650,49316,49357,50779,47767,49031,49033,49034,47312,40427,9232,50079,40277,40364,40397,40408,40493,40942,44601,48383,50307,50361,50363,50694,44961,49398,42361,39439,83825,83551,83556,42114,43584,43802,40013,39683,41735,43705,43706,43736,40114,77246,40477,47755,42468,49713,39798,79143,41416,82862,48401,45085,45662,46516,44494,49028,45644,46372,48680,51313,83969,83887,83748,83833,84064,80639,83195,83204,84831,40191,45738,48665,84103,51084,83851,39477,48406,49410,49687,49685,49681,81613,81992,82006,82398,81827,81808,39481,47430,45785,47516,50519,47515,47517,39790,43212,78197,85570,85592,43290,78483,82762,83126,83923,39745,40317,40903,41854,79157,80523,81922,81970,84156,84674,49297,77243,80308,82820,39318,40542,40562,9111,78496,80342,80491,80568,80569,80607,80646,80652,80707,80899,80929,80951,81856,81900,81938,81980,82203,82266,82273,82475,82630,82664,82802,82830,82908,80519,81965,40216,77757,41757,46869,48666,44502,41313,9168,81846,81669,39970,75268,78358,80972,81094,81412,81595,81750,82183,83136,82249,42885,83966,83452,83881,40456,50315,76989,76994,50490,77070,78887,78952,78966,78974,79028,79047,79058,79071,79111,79158,79286,79316,79323,79363,79397,79543,79646,79662,79675,79677,79814,79930,80004,80047,80052,80166,80186,80341,80364,80414,80512,80513,80579,80669,81289,81842,82502,82698,82957,83391,78902,75992,76085,76957,77225,80804,80911,80995,81245,82299,84113,84118,84361,84473,84836,42928,40018,49154,75457,77762,78923,79083,79562,79779,80799,83013,85492,77713,41132,44976,51304,42340,45790,46323,47892,48382,80937,47165,47307,47754,76511,39108,49893,49688,49691,49709,43002,50245,49710,80399,80402,45334,45333,45037,40983,40035,76082,9203,43322,43402,50970,44317,49711,75669,75715,80242,41610,41804,43250,43254,44133,44610,46282,50706,77101,81006,49899,41473,40421,49025,40808,43076,45482,75548,42854,42035,40516,56982,48404,46823,46827,49507,49683,45607,48308,42020,44818,48385,79294,39153,81706,84862,85106,42657,78381,49705,79788,80818,81957,82911,83045,83423,83656,83935,45407,45867,48384,48660,46102,50760,39756,47330,75676,75741,75746,75805,82325,82600,48399,9413,39900,42611,44524,42188,43649,43655,41374,43206,43210,43502,43950,43979,44003,44275,44992,45200,45202,46092,46917,47310,47329,47766,50846,43158,50900,48679,47315,75623,75663,75753,76148,82101,84247,39840,40642,44839,40060,76969,43590,49321,44024,45488,77993,48388,80241,80258,80273,80280,80358,80379,80408,80468,80489,47078,39904,47314,50739,77461,77483,75996,76011,76988,79305,79489,80480,80633,80852,83282,83755,83835,84612,84902,84907,84918,84920,84923,84926,84934,84946,84963,84970,84981,85022,85056,85084,85110,43835,80717,45474,43742,44184,45467,45585,45847,48169,48398,49032,45660,44188,43755,42082,44399,44938,40982,41384,51012,48020,41371,40517,42040,42742,44447,44497,46831,50108,41368,40501,83510,83642,83809,83297,83892,78807,81657,81710,81737,81782,81941,81977,82023,82046,82060,82146,82200,82212,82280,82302,82331,82397,82432,84472,76169,81832,44401,84444,43607,44339,75371,41898,51240,84054,51116,77765,43625,45056,46699,41415,45823,41002,41307,49036,49037,41377,51059,41587,39881,40932,41217,44298,44327,45134,45169,45275,45286,46125,46608,46713,46715,47087,48741,48852,44507,42394,43023,39787,44653,79452,79465,79469,79699,80641,80715,82179,82180,83095,82192,39863,39120,39638,41018,45668,45878,46114,46145,46669,47318,47322,47326,47523,47570,47760,47918,47925,47933,48405,49317,49695,50919,76007,76008,76072,78925,79899,79914,80028,80071,80418,80618,80789,80827,80944,81259,81553,81762,81955,82696,83760,39631,46469,49976,41682,9174,44549,40709,41381,40378,45035,50905,48010,45410,51167,39098,48011,44958,50134,49978,80448,41198,44673,49122,78971,79003,79010,42134,39800,81989,39884,39815,41024,51166,51307,77398,79081,79246,79264,79375,80913,45530,46004,45793,46396,42330,48674,48810,79786,80025,79951,44538,46822,41660,44910,40655,46747,42941,46737,50737,49784,49973,51098,49972,85285,85317,85275,76545,41121,41843,43321,43687,43787,43896,44814,45148,45288,46528,46973,50929,46950,40039,41054,40481,39169,40290,40293,49026,51330,76748,76754,78747,80898,80942,81481,41664,81001,48390,45865,46409,83192,83202,83203,83882,83968,83953,45969,47849,78794,49692,46555,47887,41821,84013,40931,40515,39757,43057,78243,78349,78380,78815,85427,42671,78792,39836,42988,42732,40107,40801,50321,85198,40402,48806,42183,76045,49704,42665,42441,44233,40361,43016,45603,48381,51122,51170,51263,48662,42290,50430,80876,48811,75770,78844,79520,79884,79996,80361,80918,80976,81211,81903,82315,79527,85313,40206,40267,81787,40607,39243,40722,83248,40943,47795,41099,84078,43784,42703,49699,75704,75723,75769,76027,80214,84139,43721,43688,82486,40097,49027,42005,50877,40205,40459,48667,80725,80988,81022,81110,81145,81322,81421,82009,82204,83311,83657,84894,85332,85346,80819,78932,50931,41696,84565,42485,44420,9159,79672,81752,81821,81869,81971,81998,82022,84026,39460,44038,46526,80217,49908,50052,46115,49986,41188,39310,48389,40070,40482,82824,83653,82633,83815,48168,77027,49029,41386,48081,84597,84573,50530,42225,40409,40910,40911,46881,9130,42806,84308,46339,80554,80978,81202,81276,81410,81563,81643,81784,82201,82219,82807,83096,83323,83474,80570,51060,81013,83731,84318,41592,43542,75945,75949,76071,76110,76199,82608,82690,82704,84214,84273,84299,84337,47321,49038,75952,75993,76019,84223,47324,46514,79913,79966,82897,82906,83811,75520,75731,47844,42198,42543,49042,46914,82754,82692,40576,43799,43800,46896,83179,42831,44914,39999,44002,44206,45249,44896,42598,46468,39492,43676,39684,45690,46493,46494,46578,46620,51086,51087,81793,83318,41539,49181,49876,43880,44431,46846,46847,49101,47759,49689,49698,49706,49900,50676,40868,78420,49703,78640,78824,45800,46188,39720,45487,47088,47242,80552,43561,41020,78648,78674,78676,78709,78725,78734,78759,78762,78776,78834,78867,78879,78920,78949,79007,79037,79069,79102,79201,79278,79318,79424,79509,79591,79686,79878,80065,80084,80359,46269,48394,48670,40957,80447,79941,80409,43084,46033,46217,49399,49219,39701,44569,39487,46015,48671,48673,49102,50796,39594,40614,41111,41995,43273,41564,45784,45846,39154,39137,39381,40518,40618,40742,78973,82635,82756,78845,45707,45709,45711,46075,47208,77770,43921,39115,82217,82234,82253,82374,82385,82428,82287,47522,45409,46459,48393,48392,43725,46400,46404,46410,46413,46416,46417,40089,45256,45932,46346,46503,48400,40080,42264,44797,42596,43055,40952,46348,46651,41713,46659,40148,41788,9290,43762,46162,46883,47518,43048,46100,43187,44153,44155,45813,45815,47524,50542,49323,47521,42389,42773,39913,42608,39524,49495,79790,47765,40988,44047,45289,45897,46181,46231,46242,46547,48109,49129,49130,49133,49135,49136,76271,80508,82000,82109,82162,82190,82393,44805,50982,50084,42746,39483,49318,42914,40996,43821,40066,43621,45725,48152,48559,48663,48807,49035,49039,49040,50080,50081,50082,51118,51120,51156,51157,51274,51285,45068,44008,44019,44264,42396,47325,43106,44007,49216,43873,46056,81751,49324,48110,42092,40998,43301,75777,75811,75867,75872,75886,75932,75937,75941,76003,82289,40002,40119,40521,49322,83564,9149,46131,48022,49043,76947,76991,76999,77005,77057,77066,77182,77354,77370,78262,78339,78772,79149,81194,81438,81583,82116,82961,41015,39208,77171,77172,77296,77333,79717,81405,85149,42366,82305,76967,42371,44266,43368,39186,40135,40136,40384,42345,41312,79744,41899,41094,44388,39183,39838,44702,77794,83355,84292,85354,40470,48507,43879,46069,46465,46472,79236,44337,48402,40630,46144,51117,44223,40916,46043,39121,41369,78612,42055,47023,51090,43698,85134,84762,42981,76510,39428,39398,81694,83327,82038,80583,39614,43900,43920,43923,43929,44311,44312,45114,41787,39976,44802,45214,39264,40400,40380,48668,41543,50399,42160,43767,45164,46517,46521,45191,41388,40557,49712,41725,51061,75273,48403,75500,75526,75531,75537,75546,75574,75593,75602,75608,75619,75621,75665,75708,75713,75717,75727,75742,75747,75761,75781,75793,75844,75853,75875,75905,75911,75923,75960,75969,76005,76032,76055,76092,76109,76145,76153,76170,76174,76231,76258,76344,76405,76456,76457,76467,76468,76475,76477,76495,76564,76580,76770,76833,77113,77144,78216,78510,78819,78937,78979,79045,79060,79125,79174,79973,81387,83221,84120,84122,84123,84129,84178,84184,84187,84189,84191,84197,84229,84250,84262,84295,84309,84397,84467,84474,84491,84493,84497,84607,84613,84618,84621,84672,84766,84772,84851,84884,84913,84977,84989,85015,85021,85027,85033,85049,85065,85074,85082,85090,85091,85141,85158,85226,85247,85253,85282,85284,85303,75710,77023,81298,76159,81691,81678,47966,39554,50385,49889,49892,50073,50076,50083,45506,41662,47520,50553,49326,42982,40499,50878,46201,46203,46277,82223,43758,43666,49680,49702,42116,43349,43933,44248,49023,51373,75174,75263,75374,76017,76217,76239,76307,76340,76518,76906,77151,77217,77424,77431,77440,77441,77447,77452,77458,77482,77540,77547,77553,77555,77560,77567,77570,77581,77586,77600,77619,77631,77633,77637,77646,77651,77672,77673,77685,77720,77722,77735,77745,77832,77837,77841,77862,77944,77957,77981,77988,78031,78057,78058,78061,78075,78083,76929,51375,77715,48986,47923,75455,82472,82519,47951,47958,47981,48028,48035,48043,48053,48056,49044,44847,45669,50761,47936,44516,51341,42360,83335,46672,45672,39569,78754,79398,80230,78251,40192,43128,43193,44319,44455,45702,45824,46692,50505,50562,50638,41297,50901,40472,45000,40737,43450,47761,44732,44725,41634,42548,43353,41933,50798,47317,47758,83247,83413,83459,83473,83587,83784,83348,41739,45144,45276,45737,50799,79946,80405,79910,80507,46253,82825,46994,41777,41890,51064,39673,40749,78446,78466,78476,78433,40615,40170,42093,43166,43352,45440,39206,48387,49400,49401,50626,80138,80550,43815,43024,39544,40004,49707,51165,51252,51328,77118,79585,79597,80974,82365,49898,50879,47906,51121,51241,41749,42342,44034,45047,45124,45551,45797,45808,46141,46174,46209,46212,46226,47000,47209,47353,48786,49085,51254,76683,76769,76808,77088,77133,78206,78450,78528,78999,79013,79095,79203,79244,79394,79566,79567,79588,79593,79638,79679,79690,79693,79700,79702,79710,79769,79776,79809,79841,79864,79865,79866,79871,79908,79984,80001,80010,80014,80057,80088,80144,80148,80153,80174,80348,80363,80365,80390,80394,80413,80415,80504,80576,80590,80657,80729,80864,81137,81277,81286,81302,81306,81313,81377,81535,81876,82691,82701,82726,83661,85488,42260,43483,44238,40654,46887,42984,42715,79608,41908,84056,81385,79600,47890,47889,47869,39300,39555,42856,43234,47768,50880,44726,45273,45274,45715,46274,46276,46283,47306,47309,47995,48380,48395,48672,48678,49325,49327,75997,77145,78651,78664,78679,78685,78691,78719,78760,78786,78793,78818,78831,78856,78947,79017,79184,79274,79497,79522,79624,79698,79718,79820,79911,79992,80067,80459,80546,81254,81381,81434,81531,82395,82551,82671,82718,83889,44699,79987,39191,47311,47313,47327,47333,47525,49361,51030,77203,39448,75453,83344,83409,83620,83727,83769,83280,40832,49437,75384,75390,75431,75434,75437,75439,75456,75486,75491,75502,75503,75511,75538,75544,75554,75578,75590,75630,75637,75647,75658,75668,75671,75685,75709,75719,75724,75739,75772,75783,75799,75810,75823,75843,75863,75874,75936,75948,75998,76020,76074,76081,76098,76143,76160,76178,76205,76211,76255,76266,76287,76309,76315,76360,76375,76383,76403,76424,76452,76487,76583,76609,76631,76672,76709,76719,76811,76820,76824,76826,76834,76882,76924,76943,77020,77107,77141,77186,77201,77213,77252,77259,77318,78161,78203,78213,78227,78296,78451,78594,78597,78650,78652,78806,78813,78825,78850,78965,79002,79005,79031,79051,79118,79191,79289,79321,79354,79438,79472,79499,79523,79534,79577,79667,79705,79712,79860,79906,79921,79933,80022,80046,80134,80177,80205,80252,80571,80643,80667,80765,80841,80862,81042,81064,81116,81134,81148,81234,81260,81294,81369,81436,81453,81506,81547,81644,81696,81828,81892,81913,82139,82236,82348,82399,82438,82562,82597,82651,82695,82724,82774,82794,82804,82821,82842,82870,82892,82913,82980,83019,83030,83039,83040,83110,83142,83181,83377,83406,83504,83533,83568,83591,83766,83780,83800,83822,83846,83859,83885,83900,83905,83917,83957,83974,83983,83991,84020,84025,84131,84132,84138,84169,84210,84236,84293,84331,84366,84413,84498,84504,84507,84562,84645,84679,84726,84767,84779,84818,84870,84891,84936,84956,84976,85017,85037,85057,85075,85113,85120,85139,85154,85166,85178,85190,85207,85231,85244,85293,85298,85311,85517,85519,85593,75410,76528,40429,40623,42122,43905,39829,46899,46968,43528,41632,43601,84965,84954,84805,8786,48676,48171,48111,43005,77868,45637,46788,39491,40069,77274,79022,83408,83463,83645,83826,84416,39804,40229,39916,45767,75927,50800,42563,41291,40583,79615,79681,81615,83052,83244,39437,9147,47757,45627,46590,46591,68662,47880,42786,42527,46848,47762,40339,41433,46673,48575,51296,41593,44501,44844,44884,46694,46696,40071,39459,42022,43809,43851,42021,79621,42217,9338,77185,77190,77196,77211,77214,77216,48396,43302,43357,43847,44045,44222,78264,80450,81222,81252,82240,41327,42593,76198,39490,40613,39112,44710,44717,75860,40820,82142,44615,41909,42334,76476,76522,76594,76913,76936,76953,76977,76992,77004,78353,79249,79816,80050,80997,81071,81661,82261,82490,82496,82539,82602,82710,82887,83061,83405,83534,83854,84183,84832,85187,40372,41228,49690,49701,49715,43367,42718,41472,50821,46779,77024,42999,82270,48339,49718,49902,51208,49901,41360,49329,50847,40296,42985,39958,81774,81000,80971,41545,77262,43087,84816,82793,82629,41010,43674,40075,42540,84482,82658,42584,82855,83501,83053,40817,51033,78854,44181,44273,49732,42073,42080,42388,43063,51123,47770,50986,47526,44758,50711,39200,41053,81605,41903,43116,44759,46461,46599,46601,46938,40078,83628,83326,40830,45647,48316,51183,51318,83149,45376,43596,51182,51041,51363,39795,49721,40577,79219,79304,79412,79658,80869,80903,81156,81478,81554,81788,81804,81833,83064,83075,83226,83301,83583,83630,81426,80892,42912,39157,47771,49720,49910,50086,51225,51226,41730,40463,39517,42663,46492,42461,49182,39772,40779,43855,49217,41683,46011,46415,43423,45040,45043,39817,80500,80591,80893,80444,41652,41383,48813,78232,84322,85478,42123,43476,41127,49716,49717,49903,49904,49905,49906,49907,49909,49911,84427,49912,48410,48286,45414,48681,42861,80228,50416,42968,45633,48409,46450,75401,75432,75675,75679,39595,39812,49722,83309,41555,39210,47334,51024,79458,42125,78811,82644,82780,82806,82856,82926,82899,45110,43533,41000,42937,50885,45514,46259,46260,48413,84901,82450,83841,82814,40079,40963,40590,40592,42709,45397,46749,43401,43437,45417,46680,48414,43529,44216,44218,46321,43146,43181,44310,44872,45323,46322,46940,39302,44701,41668,42052,44647,78858,78880,79181,79505,79837,82452,85498,43634,40843,84844,40053,40116,44454,44353,41311,46512,45650,82258,82264,82267,9217,41838,43532,79331,46066,41939,40073,51316,78875,40895,39989,79525,9426,49223,79560,48684,51124,48685,43818,39932,78752,85453,85590,78442,78493,83231,45039,45416,47527,48412,48416,48683,51184,51186,51374,51377,45745,48415,50908,49328,42559,50100,50101,78863,80297,80396,80622,80709,80742,80793,81376,82355,85530,51125,50102,50780,39173,41917,84096,43641,51062,47341,47343,49046,49047,49055,49057,49058,49059,79224,79280,79285,79854,80220,80223,85287,76923,40343,75907,76523,76641,76912,79595,79682,84340,84422,84568,84895,84917,85053,85251,77686,85318,76269,41283,76330,50266,42896,43169,82979,83151,83154,83185,83210,85193,42820,44433,47024,76927,76944,76954,76997,77001,77015,77021,77071,77098,77156,77168,77263,77275,77334,77351,77359,77368,78155,78157,78259,78298,78374,78382,78400,78516,78518,78534,78536,78554,78578,78588,78604,78615,78657,78703,78898,79042,79123,79332,79348,79753,79782,79801,79861,79947,80305,80749,80816,80872,81174,81838,81912,82052,82672,82708,82817,82928,82936,83083,83161,83272,83372,84815,85343,85361,85368,85463,85555,85566,85574,85595,45390,77094,78375,78782,79070,79349,79631,79768,79830,80017,81978,82031,83527,76919,77348,46800,46491,47018,47773,41270,44565,43833,46158,41756,46853,84014,84001,83647,78886,78832,78869,40823,80592,81474,49107,40084,45903,50948,78637,40705,40710,43204,40707,47344,47774,79708,42895,51112,49735,83713,79604,49918,49920,50089,50090,50091,50092,50093,50094,50095,50098,51126,51217,51380,49924,75216,76560,78641,78805,79029,79502,79558,79576,79599,80356,82616,82931,83000,81341,81214,41193,50432,42206,41856,41772,9440,42202,39632,40090,45960,46698,43283,50902,49330,51127,43453,82239,45595,48170,48691,49407,39583,42032,44666,42449,39818,50825,42133,42443,84576,84714,44722,84566,50311,43534,44770,44773,45306,45347,48424,80891,80881,78461,78683,78750,81617,81161,80728,85260,50459,50461,50466,50467,50468,50469,50472,50480,50484,50487,50489,50492,50525,50531,77314,77356,77299,39824,42566,40936,49412,79868,84665,76901,45260,46746,84810,42248,75985,46849,75532,40641,75670,51052,45427,48427,48695,48816,49053,49096,50103,50564,47533,42828,49729,77149,78171,78728,83249,48433,48821,78170,78231,78413,78441,78570,78626,78814,79542,80685,81014,85526,78492,40058,81501,51295,51298,9436,40086,39833,43662,43701,43702,47528,50575,85206,40850,45894,9112,9241,42223,40036,81104,85390,82783,82538,41263,85428,81645,81634,43046,45441,48693,49402,44750,84095,79335,84758,84854,42210,39224,50516,75883,75904,75955,76097,78783,84776,40094,47537,49333,46789,43150,43154,77587,78913,78948,78986,79026,79959,80675,84627,84839,44320,46364,75649,50735,75572,39347,47015,84079,43184,47897,48435,77302,51088,51013,40387,41952,42109,42481,42881,43997,44247,40105,50743,44925,40118,50740,80961,83717,47529,78385,81127,81484,81783,82027,82494,82706,82717,83057,83113,83168,83383,83416,83671,85083,85335,85336,85362,85596,85005,42804,83427,77267,75779,42177,45236,45799,51158,51378,75525,77292,75521,42176,85562,50732,39844,40473,40524,83446,42284,46832,45538,84073,40810,48425,43014,40546,84039,48418,43115,49737,50971,79303,79656,79684,81611,83407,41581,84897,43545,51221,51382,39712,48128,76293,76334,76570,76664,77286,85146,85165,85192,50313,41083,43039,76279,76696,43898,79791,51027,39724,51212,51379,43717,45674,48429,9127,40256,76839,40337,39284,49727,82563,82777,82885,40007,41046,41969,45667,46074,75918,41595,46119,48436,76760,48112,49332,44901,44980,45375,42918,45535,39328,41047,42025,47340,51092,40051,45998,48428,42880,44234,40437,47338,49736,9151,39802,40547,40593,43597,44436,45143,45357,45872,46063,46085,46221,47331,48419,48464,43298,44493,42530,45771,75696,75705,75712,75714,75730,75735,76058,76063,76245,76247,76302,76415,76500,76501,76506,76633,76643,76644,76742,76758,76767,76787,76846,76853,76859,76860,76867,76876,76886,76937,76939,76985,77014,77018,77205,78928,79212,79350,79550,81183,82595,85201,85214,85223,85279,85291,85295,85306,85312,75734,75690,41973,39939,39940,78655,78669,80003,80070,80247,80335,80880,80945,82614,82796,79189,80239,47337,47777,44737,45168,45328,46766,46783,46958,40098,43222,40735,40218,50762,40104,43589,9188,48689,42767,43883,44094,44786,45987,45989,46065,47910,47912,49041,50303,50305,50398,82360,82405,82527,82582,82617,82750,82761,82861,40914,41361,42318,42336,43853,43889,44267,45451,46676,44943,49738,49922,83980,43977,44491,43142,41582,43647,47772,49191,47904,49123,51093,39761,40626,83401,83541,83997,41420,78557,44417,50211,39262,44540,40711,41398,43391,45924,46645,39489,80229,80266,80271,80298,80231,42063,41685,43418,43682,43685,45258,45325,46683,47046,44791,76618,76673,77407,77763,46013,48752,48423,48694,43139,43703,50319,41955,43521,44964,9403,39766,42592,39921,40394,40396,40575,40597,42159,42373,50228,51034,43733,46080,50677,39702,50518,76025,76042,76075,76182,76623,78354,79116,79216,79263,84151,84213,84290,84381,84809,85195,85264,85305,85315,75913,40301,75942,84647,43224,44583,44841,45014,45036,45201,51128,79655,79663,80328,80560,81496,46864,39367,79643,41832,40689,41022,81723,39762,40629,42387,78123,45504,39313,80629,39842,44830,46143,46431,51331,51332,75167,75193,75203,75250,75278,75324,75337,75343,75393,75736,75815,75817,75837,75928,76066,76106,76387,76484,77295,77397,77414,77494,77496,77548,77577,77610,77660,77696,77716,77721,77732,77759,77818,77854,77965,77967,77990,77992,78064,78078,78084,78087,78090,78104,78295,78469,78733,78746,79169,79262,79967,80535,80632,80683,80783,81164,81334,81407,81866,83264,83353,83387,83480,83513,83663,83775,84155,84174,84227,84329,84336,84386,84430,84456,84495,84496,84506,84549,84663,84680,84811,84906,84945,85020,85096,85327,85404,85450,85483,41152,81519,41674,43095,40922,41640,44852,75262,77628,77984,51369,50903,43974,42886,49235,78843,40150,40391,40392,40927,42605,42612,42685,43135,43623,43999,44000,44074,44819,75814,75816,75827,75845,78865,79237,79743,82902,83017,84147,9446,42954,76220,47836,47939,47953,47969,47976,48000,48005,48018,48042,41820,43978,44811,46681,46990,78144,78146,80520,41815,41247,39908,41315,49124,75179,80655,81346,81490,81526,81571,83756,39822,40304,40727,44102,48897,81439,81485,42661,43361,43417,80593,45470,47091,47093,75722,81521,83234,46923,44803,9129,39198,82553,83033,82612,40765,76434,76437,76449,79711,81055,81310,85019,85131,85151,85155,85310,50678,43085,76363,79206,45601,45875,46616,49386,47336,47531,47535,47536,47778,48543,48677,49051,50586,48583,83298,83818,82015,83607,85379,83606,83623,81607,83167,43581,76730,78175,78767,81939,83216,84238,75830,76400,76460,77111,79601,80000,80282,80754,80828,80906,81051,81115,81123,81191,81551,82313,82389,82501,83051,83055,83145,83444,83584,83598,83910,83932,84136,84523,84616,77207,50978,41568,41913,42971,43113,43513,44236,44705,46920,43477,42214,76698,76714,76744,81261,76669,42458,45866,49733,49921,42127,46711,84053,39247,81475,82769,83435,51436,43044,48114,49739,51218,75156,75438,75499,80044,80982,81600,81929,82129,82170,82185,82760,83074,83220,83507,83913,84237,84838,51360,51384,83359,83382,83424,83738,39179,43882,48397,45479,50591,50599,50614,50616,50639,50640,41150,42712,39488,45973,43759,83256,83445,83654,84107,43644,42186,78494,81964,85179,85215,50824,42590,43671,85391,78210,46952,48331,44315,81522,81627,81748,45423,48417,46101,41485,45943,45946,48116,48172,40774,39557,45549,48420,48422,48690,48818,49054,49062,49724,49731,80081,81052,41590,44506,79138,79572,79617,80449,79938,49926,81498,81802,82703,78172,78415,78429,78852,79035,84516,85469,85573,78580,78569,41800,43771,39371,83947,40716,51257,51258,40311,40519,43956,39468,42425,43261,50618,45046,48438,85316,78048,80398,85246,85300,85302,42613,44579,76848,44111,45015,45024,46961,46543,40092,41168,39485,42363,49193,42654,82640,82599,43718,43854,45082,39619,40032,81555,40122,46049,75315,75350,76790,77204,78196,79185,83744,84823,84987,85297,78168,75311,75547,76281,44528,80249,79146,45666,48815,48822,49056,49923,49925,50088,50875,79849,80756,47538,39326,39556,40748,43857,79596,83425,83490,83522,83580,79962,83563,49725,75881,79217,80343,81437,82455,82977,83178,39659,45517,78202,78749,78874,79736,80115,80203,80662,85490,40313,41057,40295,39376,44550,80454,80614,83108,44888,45271,84057,43262,45439,48439,45425,47041,48421,42506,46110,47916,44059,44154,45303,85401,39593,40496,40103,40193,79461,51238,51239,46272,42768,48814,81777,49048,43841,45436,49742,41319,49596,48432,43535,42178,43723,41876,39495,78166,78241,78543,78721,78958,79131,79266,79292,79357,79926,80334,80956,85412,49914,49915,44265,40997,49163,43848,44099,45592,46296,48175,48820,49723,49726,49740,49913,49916,49917,50099,50801,44838,46687,46689,75452,76611,76646,76858,76890,84611,43068,76864,44776,40055,76254,76298,76327,76349,76366,76393,76433,76443,76464,76533,76576,76630,76685,76711,76779,76802,76832,76836,76868,76922,76968,77025,77046,77085,77132,77176,77232,77290,77342,77369,78182,78236,78268,78293,78366,78456,78519,78610,78667,78738,78808,78823,78828,78927,78934,78950,79059,79101,79161,79164,79281,79455,79467,79485,79568,79707,79887,80027,80672,81037,81149,82272,82939,83728,84272,84328,84407,84420,84586,84600,84614,84706,84709,84741,84777,84782,84795,84826,84855,84859,84868,84869,84910,84960,85028,85093,85132,85144,85172,85175,85202,85218,85242,85290,85299,85367,85572,43527,44870,44954,47339,47776,49050,47534,80851,80914,84651,48434,76426,76559,83275,41349,82881,78954,49734,39979,39849,49049,84031,42473,39399,41294,44552,76656,78187,78205,78249,85437,85476,85527,85559,76425,42296,77091,79256,78322,48687,44473,46200,43300,42673,39633,50609,50607,44669,79223,78319,49061,42465,42633,78735,40109,84656,84899,85025,85182,41653,84347,40852,43934,44168,50733,43488,79981,80746,80897,81028,81140,81159,82303,82543,82648,82795,83223,82262,81665,79969,41279,44212,81004,45844,45855,45953,45843,40465,50937,39616,48437,51364,45849,43336,42684,41373,42325,46911,46639,80612,50679,40128,43796,39226,47842,47850,48001,49074,47944,44591,44559,39502,50680,49308,49339,83393,83802,83831,83395,39603,40388,40389,41086,43324,43287,42757,9199,40635,40953,48692,48817,50881,39563,41617,42845,45997,50828,49470,40548,42326,46613,76873,77106,78822,79006,77805,49247,83429,83403,9135,42690,47779,41055,42704,40156,9139,47895,47909,47921,47932,47903,47345,47539,49334,45594,46280,48440,48696,48823,49064,78063,51272,77717,51281,41993,56955,45098,45443,48306,48441,76162,51192,51385,83265,49200,49928,50802,45457,46425,50682,47784,49336,42375,75253,75441,77352,78224,78324,78658,78715,78841,78888,79337,79352,79530,79842,79896,79900,79924,80420,81043,81447,81839,81930,82066,82232,82444,82458,82548,82657,82683,82778,82954,83566,83722,83729,83757,84172,40110,83627,50822,80018,45391,46624,47034,39476,41620,46579,42142,44261,46062,48519,45561,40428,47545,47543,50973,39506,39571,80455,80479,81021,81898,82377,82568,83279,80518,81852,81831,41243,40672,41119,42513,42825,44294,46193,48675,49187,39275,40077,80246,80736,80777,80831,42627,41681,44906,48452,48698,49070,49343,9119,44554,42430,40121,84745,45500,47788,48202,77341,79105,79439,79450,79506,82850,83194,83616,83649,84793,84905,85422,79205,39744,43036,41873,44764,44775,46678,46679,48652,75239,75246,75248,75405,75655,78136,75322,39335,42771,40596,43711,48719,44798,80901,80993,81044,81125,81147,81193,81199,81201,81215,81865,81973,81981,80871,41469,39316,80486,80501,40072,81173,81807,40610,83379,39613,41008,83097,83066,83056,83111,47822,47823,47825,47824,43689,50104,43291,41616,43465,44414,50608,47546,49337,40232,40657,40658,50975,47354,46299,49183,75247,75382,76080,76897,80131,80708,84202,84242,84676,78892,8772,45766,46265,48297,48443,48707,48829,51160,51386,48173,39726,41689,49138,41549,42204,45476,41733,43219,43458,44028,44031,44136,44835,45122,45139,45219,45283,46315,46389,46658,46695,46764,43714,43981,43156,43569,43570,43576,43609,45026,47787,48703,47358,47356,45502,40933,42180,45160,79706,82078,82189,82323,82214,82284,40308,79972,41627,45041,46532,48442,45555,46901,75560,75561,75697,75903,79557,81226,82676,83528,84128,84201,84602,84992,75627,50209,44930,41392,43842,44485,43778,39839,48700,44292,44404,44405,44469,44578,45223,47049,47054,47056,84275,44599,43710,41982,42503,41235,43775,41678,43989,43377,45449,48450,48701,49082,40221,84166,84425,40690,43318,40268,49198,44630,46326,76272,47350,47782,44949,85289,47348,77208,77257,77268,77327,78287,79739,80149,81069,81651,82593,82746,83450,84749,85472,75237,81244,82526,82680,83229,83257,83575,82645,81235,82609,82491,82525,45775,45870,45876,46048,47197,47369,49073,76893,83173,45856,42269,39936,81392,45826,46072,46985,40223,39855,42003,41648,9243,40971,43699,50823,47346,50781,49341,47544,49338,50619,78338,85398,45598,46306,40520,43176,46888,49753,78924,49751,45432,68683,50683,42681,48831,49239,49240,78481,81053,81425,81486,81525,81686,81991,82259,85457,81588,39954,81786,44340,46643,47125,47192,47260,47301,47365,47405,75173,75194,75197,75244,75255,75279,75298,75301,75308,75349,75372,75564,75842,75920,75934,75939,76010,76171,76410,76450,76465,76614,76655,76726,76749,76813,76825,77838,77844,77845,77867,77896,77900,77907,77976,78006,78012,78014,78015,78021,78032,78052,78080,78093,78098,78114,78143,78341,81655,84144,84152,84478,84522,84587,84698,84866,84986,84991,85078,85197,84452,75297,84974,44245,46972,48208,48705,49071,76962,76995,77062,48445,48706,45447,44427,45455,48456,40155,50512,40603,44006,44042,44044,44066,44090,44092,44106,44129,44203,45231,45419,45452,45524,45575,45692,45901,46134,46254,46320,46388,46439,46463,46476,46515,46534,46538,46585,46632,46661,46666,46688,46693,46706,46717,46763,46775,46780,46976,47076,47086,49019,41736,41745,48454,48451,46426,40628,47785,47551,47783,47789,50941,49340,41561,43435,42278,44366,44571,75692,75743,75767,75776,75812,75831,75839,75935,75947,76029,76052,76059,76077,76111,76134,76157,76180,76207,76208,76221,76230,76335,76903,84130,84180,84204,84241,84252,84278,84287,84324,84339,84354,84376,84385,84408,84439,84539,84592,84646,84746,85157,85162,75711,40349,77322,39545,40126,48117,43072,41277,75534,77945,43021,40937,42776,50707,39418,42241,81850,84927,78939,39653,77271,44611,41805,41554,42289,43939,44634,40637,42761,40305,40444,45434,39857,50503,45090,42922,43740,45128,45311,41011,45787,49937,47961,42322,44416,48195,83960,48825,42068,40314,49697,48827,48206,47550,43253,42498,44137,40166,45698,49744,76412,76757,76764,76776,76900,77189,77191,77270,77312,77345,77346,77360,77365,78184,78506,78617,78661,79088,79813,79833,79935,80005,80045,80058,80102,80126,80208,81027,81372,81959,82196,82260,83338,83937,85042,85348,85408,42864,9361,50233,76608,49229,44918,42710,39116,40921,40298,39770,47359,47792,47075,44199,44879,43223,43123,41088,40432,41125,44367,43915,79980,46002,81291,81340,81401,81456,81636,82241,82361,82373,82392,49436,81354,81757,81268,40769,45939,51014,85168,48122,39141,45095,49065,49084,43754,48176,47360,47366,47367,47843,47861,47862,48449,49066,49067,49068,49069,49077,49078,49079,42763,47361,47368,50630,49344,47549,49404,49936,44780,45318,45321,77105,43320,43482,42729,39752,39786,44859,49331,50684,50665,42833,41095,82079,83124,49072,50991,39859,47351,49080,39260,40992,43657,44084,44968,48118,49743,49938,50106,50107,50109,51129,51387,76139,79225,79575,80908,82252,82779,83106,84908,41050,50882,49940,42658,77538,78443,42692,50904,39627,44626,40567,39239,41182,50848,9298,83965,45248,79367,41500,43663,82573,44095,46030,42614,47863,42668,39403,40860,39434,79634,40225,44424,47051,47791,79653,39298,41516,48447,77583,51340,84933,82286,41461,39870,46197,49747,40890,42408,42711,45740,46802,47781,45926,42738,50935,50938,50943,50945,50946,50947,50949,50951,50953,50955,50956,50979,50980,50981,50984,50989,50992,50994,50996,50998,50999,51000,81829,49932,39559,41562,42232,42192,43731,44274,44301,44756,45948,47355,47357,47730,48446,49075,49755,76575,76797,77109,78418,78605,78627,78742,78840,78964,79190,79209,79254,79271,79400,79725,79800,79950,80326,81314,81414,82042,84876,84944,84962,85062,39558,41156,42358,44324,42651,44465,76441,76537,40049,46064,40322,39622,47540,50641,50782,44986,48828,40870,40363,82747,43507,45529,82937,83004,83080,43275,51066,42717,81945,81779,42492,42383,47352,82523,44291,45163,45166,45297,46240,46988,49749,49745,42137,49931,49190,75929,40462,41019,46844,41326,41604,42929,81232,82430,42714,39946,43133,41524,50511,77650,43949,9128,44085,42064,46205,48444,48699,48826,75984,46641,41862,42071,42072,42076,43611,43613,43605,77623,39803,45445,46939,45836,51133,44617,39568,39851,82382,40291,82470,40011,45199,39658,40321,44331,39858,80531,80469,39610,41091,50685,40315,77053,77065,77078,77099,77124,77129,43568,39861,44820,40894,46042,46759,75426,41975,44169,45835,39883,41439,42314,50344,47547,50653,47548,41320,50621,51294,76459,43694,80333,46089,81363,81397,81454,81502,81541,82655,83035,83103,83572,83776,83954,83020,42576,45505,46444,79052,49081,39623,40328,45499,39462,40068,42813,47756,80664,83676,83876,83888,83639,83689,80376,43497,45765,47780,81062,84327,78177,41986,79329,42983,50954,48120,39892,41990,76508,48702,48448,40270,48824,78411,78941,78996,80980,81067,81107,81324,81368,81631,81862,81943,81974,82098,82132,82153,82165,82254,82558,82656,83047,83363,82310,79346,81128,39893,82424,39233,40644,44041,43408,51067,39561,51312,81884,81946,40203,42857,44349,47876,47891,47893,47935,47956,47962,47972,47978,47982,48121,49345,50105,75661,78162,78484,78961,79072,79094,81083,85489,85552,85579,51134,51131,39291,50701,41104,85454,75601,75613,79210,80733,42842,49083,39626,39675,40271,40443,42128,42574,43201,44542,45106,45108,45112,46479,46480,47030,47052,47085,81523,82488,82506,44526,45048,50312,39099,81590,41273,77609,49076,50207,84414,84764,84841,81185,48763,83973,45994,9214,48455,47840,45038,84102,41271,42090,82229,82446,82618,82631,82674,82735,82809,82859,82215,41443,83190,83224,83292,83328,83332,83380,43860,43885,43941,44589,46866,46885,43486,43861,49830,40037,46073,46429,75160,75166,75212,75213,75214,75217,75245,75256,75266,75267,75269,75271,75291,75312,75320,75326,75327,75328,75331,75359,75365,75366,75395,75460,75494,75528,75591,75888,76214,76317,76326,76435,76503,76538,76658,76686,76710,76821,76946,77068,77072,77635,77640,77641,77644,77658,77664,77665,77703,77714,77727,77752,77753,77798,77804,77843,77933,77935,77940,77952,77956,77966,77974,77989,77997,78008,78022,78036,78040,78112,78120,78128,78132,78145,78551,78666,78771,78789,79443,79674,80156,80307,80436,81011,81916,82186,82818,82971,82978,83101,83531,83540,83544,83696,84141,84190,84268,84270,84289,84348,84441,84643,84701,84786,85196,85356,85584,77614,85122,77790,78129,39890,49746,47362,39611,39400,42634,46148,49597,40594,81867,40753,81778,42207,76978,50482,78816,39981,46353,41287,44018,45460,45596,46886,48460,48709,48710,42086,48123,49346,41763,47374,44217,83196,75379,39635,77158,78192,78711,84871,77828,51245,51246,51268,51269,51305,51306,51333,51334,51350,75917,39227,39427,77237,79050,40664,50521,50537,50546,50552,50558,50567,50568,50569,50574,50582,50587,50602,77165,42532,39467,40631,40401,40755,40757,78821,78873,78956,79322,79364,81343,81520,81549,81803,82417,82480,82504,83041,83144,83176,83255,83466,83506,78833,82518,9136,78848,9134,80599,42286,47373,48473,48617,47799,80574,46127,42656,45003,43773,45643,46568,77504,79775,83006,45665,45983,49819,48458,46139,46169,45559,45562,45569,48461,49377,48832,49086,49347,42733,78189,79734,85249,78334,78337,78401,78635,84948,84957,84966,84969,85008,85011,85115,85164,85176,85330,85442,85545,41643,41886,44678,9267,41497,47552,47556,47555,47371,47801,49063,43572,50529,43959,42931,39896,40272,40276,41027,48470,78498,79108,79720,44631,85003,42991,40172,41953,42179,75448,42664,43232,44720,44891,45077,46588,48471,49413,49415,49416,49417,49418,49421,49428,49432,49435,50325,50349,50350,50933,9122,50327,78544,50224,42701,39135,44221,43583,43604,40273,49756,42205,46026,42619,39294,39643,39867,51390,51193,46202,42926,40187,81359,83956,48462,49089,48715,46178,41223,39707,42660,80314,75321,42936,40676,40939,79709,79923,81262,82621,82955,84007,84011,79589,79990,45566,75152,42238,75855,42610,79373,40274,40829,42253,41687,50185,76920,76849,83016,82094,77063,81253,75569,79151,79220,83112,83102,47378,50976,41786,46359,46989,49757,42578,46252,47793,78731,41429,41894,45386,48952,9226,80921,39170,42810,43132,44457,44887,46955,46956,75416,75422,41879,82904,82465,83104,83129,83736,40530,81515,83140,8774,85413,42327,44536,46928,81822,82118,82714,82999,79476,40656,82087,43004,39297,43769,80338,43475,79194,40133,46211,47541,48630,41229,40946,80306,48472,51028,46691,39902,39783,43592,79895,42320,79931,42779,39681,9190,43469,43543,41637,39909,46213,46609,40124,40738,39905,40587,49125,79853,79975,80431,80604,80930,81166,81189,81218,81317,81759,81881,82292,82333,82354,82462,80112,79735,79978,80037,47796,40878,41131,48465,51227,41363,41260,39466,43998,44001,44687,44688,44689,44695,44696,45469,48476,48477,48478,43751,43127,43744,79985,39416,39612,43798,42519,46971,42294,43218,40995,82466,82445,81090,82834,82415,82942,43407,46496,44561,42620,42646,42765,42460,41331,47370,48711,42873,39718,41306,41413,41612,41658,42042,42057,42065,42376,42391,42418,42446,42466,42472,42585,42591,42617,42622,42625,42691,42754,42915,42920,42977,42980,43010,43064,44224,9277,49941,39934,40840,51299,46510,48541,39327,42407,43588,83417,83678,79834,79803,44674,39565,40766,82866,82953,81210,82531,79822,82757,42958,39469,42949,44253,45165,43109,41417,63169,9330,42400,46016,44664,40316,50719,44676,46733,47883,45174,40157,40284,40285,40287,41286,41397,41621,41974,42452,42544,42558,42572,42595,42780,42785,43050,43635,43643,44345,44556,50252,42666,42662,42362,42814,41296,41335,42392,42630,51068,41072,48714,48833,81936,80285,39942,43045,41041,40668,42902,9155,42444,49945,80289,80605,81518,81689,82295,82339,80120,41822,41882,78304,81895,46027,43022,83987,42653,84510,40756,81931,46154,42798,76502,43646,45744,76679,76582,76687,78890,78919,80874,80922,81002,81092,78562,44283,41994,43306,44178,44989,49758,77039,78038,44385,46451,51171,51388,39107,80392,78313,78438,78408,46155,40042,41618,43185,51322,48101,77466,39966,48457,49128,49134,50570,51077,51083,51015,80724,80710,42851,42781,42899,42095,83077,81632,49141,49207,50714,9228,51025,41208,40527,41830,42974,41035,45617,47733,51016,39105,48466,51069,40502,42535,9223,43763,78723,9201,48469,49946,49760,40918,40772,46741,51070,49759,45832,48474,83206,43393,76599,76805,76593,42208,44124,41367,79372,48185,50664,47554,76304,76306,80339,40506,48113,47377,42013,43813,47376,41734,41151,42154,42113,44374,80383,83008,83048,83134,83153,83958,83986,82888,81255,45746,75435,75454,76535,76753,77045,77060,77077,77143,77164,77187,77219,77220,78297,78639,78872,79361,79517,79839,81816,82372,82560,84438,84471,84715,85026,85167,85355,85373,85486,85496,77100,76479,41650,82138,41720,50851,76168,76232,78363,83329,84206,50810,50806,45557,45560,56960,40188,44061,46824,46825,46826,46828,46949,44962,49761,39769,40701,39733,42491,46882,79883,43781,40821,41542,42039,49863,50119,39697,43293,45027,42313,42227,43182,42085,42775,51194,51393,40647,40602,48190,40522,40605,40120,40554,42539,43862,81118,84821,50734,39100,51071,39520,82368,81129,81273,45294,47010,45159,45180,48467,51072,46001,77227,77236,77223,77285,44982,43924,43925,46225,46421,46423,42793,43069,39847,50783,41096,42884,41583,45527,80544,42432,84041,84547,84559,84595,84814,84533,79356,79407,79415,79425,79445,79456,79486,79492,79565,79619,79649,79673,79691,79845,79918,79925,79960,80036,80041,80049,80080,80100,80114,80182,80362,80425,80567,80691,80788,80889,80924,80949,81133,81219,81373,81662,82369,82419,82457,82479,82520,82581,82681,82709,82907,82956,82990,83073,83236,83258,83433,83518,83547,83745,83765,83946,83967,82981,79214,79404,48199,41512,42397,42386,44972,43595,78154,78434,78477,78575,78877,78957,79200,80184,81985,82029,82729,83182,85513,85525,85540,85568,82342,85407,85394,41268,40625,50690,46313,45408,48721,79763,44230,46929,47379,47804,47806,48837,42431,45794,40081,40639,50114,40483,40485,40528,46051,48484,51337,83718,84006,42553,39737,42173,43878,82225,50221,76321,76368,75778,43058,48479,48720,51277,79510,51401,49160,43380,39708,9117,50687,42946,40450,40452,78159,78234,78479,78489,78727,78943,79004,79020,80868,81081,81221,78585,42787,41751,45658,46391,48487,45964,42841,50705,9115,51397,49762,42650,51398,49766,85145,81708,81843,82176,81630,42989,43404,43446,43681,42324,83268,83281,83373,83402,83434,83491,83785,83824,83585,39739,49767,39325,47868,49637,46720,77799,77951,41573,42556,43537,46302,39380,41714,40181,45477,47067,47557,47558,50675,80452,49126,80702,40798,40825,81337,82554,81282,81362,51400,43179,44590,50438,49184,42957,50273,77122,78646,79965,80175,76494,39674,41957,43579,44281,44580,44826,45050,45324,46622,42583,43678,48838,50113,44672,42897,40082,46081,44121,44127,48089,48179,49093,46873,43890,39621,41196,41755,41818,43230,43479,43909,43984,44089,44232,44574,45032,46290,46611,46725,46726,40182,81197,41302,42046,43667,43677,50341,47816,47817,47818,47820,47821,48180,43020,39343,47807,42016,41333,45411,46038,49342,50686,47380,47559,45788,48482,40643,82901,82962,82991,83026,50135,42908,82864,48918,49092,50888,50906,44306,41997,47385,49348,56961,41988,40125,43259,45212,45433,46130,46249,46250,46267,46268,46373,46395,46535,46565,46942,46945,47220,78292,78463,80526,80527,80577,80781,80803,80838,80936,80970,81102,83171,41328,85125,40680,42987,45245,40226,83059,84048,44489,44767,39943,45246,78309,41963,42315,83090,39927,42919,39920,42281,79230,47381,48836,49090,41063,48718,77803,77813,80458,47560,50775,44392,83385,51017,44352,50115,45239,46040,46499,46595,47803,49091,75484,76028,77395,77406,77417,77439,77450,77561,77612,77639,77657,77835,77150,43383,47382,49161,48483,43371,39741,79586,79612,79767,79787,80089,80147,80767,80890,81810,81966,82669,82771,80732,76677,76693,85308,79291,84488,76332,76308,76356,78748,39729,42994,43038,44358,45696,48485,48835,49094,44547,41292,78109,39231,42110,43491,43692,44816,44817,45235,45237,45262,45510,51135,51159,75183,51399,42866,48480,48722,78546,78555,78636,78741,78744,78836,78907,79177,79282,79314,79370,79419,79479,79482,79491,79501,79513,80274,80947,81108,49797,84084,43144,44698,9163,43894,48717,48839,83749,83790,43199,43577,43137,46642,41175,40335,51139,39321,79944,79955,79957,80053,80087,80118,80275,80321,80367,80476,80536,80771,81445,79970,50116,39948,48481,78314,83494,85456,85481,80619,81574,83117,83128,40093,83317,83577,43107,44004,44013,44056,45187,45205,45424,46769,42420,49708,39305,49165,40478,40480,40874,40945,40985,40944,40763,43264,51188,51404,42986,79234,79297,79327,79391,43242,42586,80860,81009,43903,44063,45070,39665,44871,45012,39967,43749,49770,43943,44380,40553,51137,82062,85571,51195,51394,75189,75283,75333,75386,75391,75427,75540,75595,75607,75751,76130,76133,76274,76439,77380,77531,77598,77654,77810,78013,78027,78046,84146,84220,85375,85382,85433,47070,77594,75629,51396,45589,80932,81345,81558,82985,83114,83188,83333,83546,83619,83637,83697,83812,42978,45871,39764,80861,47383,47384,49349,51094,43012,81074,81070,49771,43430,45073,46008,42975,78774,51136,50117,39440,39962,41622,41675,39961,42764,81357,41458,76783,41719,46176,41275,41586,42074,43834,45224,45227,45343,81095,47399,76194,76635,84135,85171,76094,44244,39332,41289,41309,41470,41529,41807,41932,42455,42477,42547,42579,42696,42737,42923,43147,43149,43982,44418,44421,9307,43062,41138,48834,44046,45831,48488,51144,51172,79393,49127,47877,48731,43315,45154,44922,46046,83896,83907,83943,49962,45822,49781,49950,81499,82596,82766,82958,45520,48491,77298,42571,39136,39747,40138,43686,44807,46842,79685,79701,44661,41347,41322,79748,79626,50199,82642,51018,47100,48843,49960,51073,50126,83979,84058,82328,82439,82598,82896,47566,49350,50697,79888,51407,51408,45588,51140,48494,81823,42464,51417,50829,43008,48021,79215,79226,79229,79267,79309,79330,79388,79422,79459,79620,81952,82075,82131,82175,82230,82306,82436,82521,82666,82721,82781,82810,82851,79240,40640,48729,42976,49095,43130,46536,46574,83879,42930,45850,43480,49957,49966,46278,39982,45018,45809,48847,48493,49185,9449,50688,48246,48426,48723,48845,51173,51411,9153,51412,39628,47106,47387,48124,82243,83085,83752,83837,82054,44450,41128,39139,45984,42514,43013,40862,42411,45175,45267,46908,49775,51145,51352,47404,39668,77460,77465,77469,77471,43951,40759,49778,76431,76483,76512,76637,78517,78568,79025,79183,79484,79783,79983,80371,80916,81227,81495,81790,81934,82172,82549,82705,84105,84483,85183,85421,85594,76605,41203,42505,39197,41442,39461,44225,39885,41507,43376,43378,43416,44621,44899,41410,47845,47561,49955,49964,49965,50125,51273,50122,50124,51143,51413,47563,39484,41619,42252,43575,45088,45092,45336,42875,48495,41158,42898,42166,47403,84088,47099,40771,40826,41213,41870,43354,43504,43525,44271,44598,45238,48726,48773,48846,43500,44977,47107,44909,44927,44757,44422,49958,48125,49384,75524,75533,75553,75600,75617,75582,75583,75588,75645,50696,76124,84200,77916,75385,49222,45370,40040,84044,84052,49351,84519,39765,41178,47101,47102,47104,48126,48842,49369,48728,39978,84049,84071,84086,39768,82682,39299,39389,39286,47885,47871,39935,44558,48844,50853,42311,82803,46349,40505,75938,77344,80056,80075,80113,81320,82530,41694,82074,48178,48137,49155,42028,80311,81446,46167,47393,47398,42138,44257,44258,45301,60076,42853,42257,44515,50887,44467,50254,43811,43040,43091,75672,49440,45614,39391,76888,47391,51146,78910,78930,79065,79136,79198,79221,79312,79320,79353,79387,79396,79538,79563,79579,79648,80762,80836,81223,81275,81318,81888,82869,40619,40999,50008,47035,80608,79182,79982,79011,39245,45978,80611,81455,81552,81510,81347,82652,47950,47965,48489,78677,48727,78601,39690,79737,79964,80313,80462,81580,81599,81653,81712,82020,82396,82555,83218,83230,83320,84021,45845,39796,79780,46445,75764,75893,75944,40896,46093,42486,39868,85059,85086,79466,43425,45952,45923,47569,40600,43474,47105,47401,47562,47571,47811,48210,48490,47568,50708,42029,80234,83177,83295,83364,83488,83521,83578,83708,83959,83988,48724,51022,39725,47103,47809,48782,49956,50153,50168,50180,50148,46983,77228,43011,41892,43188,44334,44335,42131,45837,47567,49354,49772,49948,82182,82209,9166,45610,75403,44671,45965,41863,44994,45995,46097,46195,47108,47342,48134,48181,48492,50854,41197,79227,8780,41210,43540,49146,43863,43875,41400,49519,77736,40504,49352,41240,42659,43672,51020,49773,49776,49779,49949,49951,49953,50120,50121,51142,39866,80278,80291,80330,80336,81206,41173,9349,76067,41901,46592,51376,78684,78729,78656,49774,82237,41085,50993,39160,40532,41147,41774,43612,44921,46836,82482,42419,76446,76493,77174,77202,78261,78383,78464,78515,78751,79188,79570,79607,79644,80021,80150,80366,80524,80693,80954,81035,81058,81532,81564,81614,81619,81709,81727,81857,81877,81947,81962,81986,82012,82140,82222,82291,82418,82442,82572,82831,82844,82893,82918,82946,82959,83025,83060,83141,83217,83285,83428,83489,83604,84849,84858,85127,85468,80977,41644,81935,41930,39947,43697,40770,43074,75910,75919,43151,43152,45133,77789,45683,46010,46149,77075,39888,47565,49355,50730,41839,39646,49376,46420,43995,45833,41724,42753,83567,41495,44463,44545,41215,50573,50578,50580,50585,42250,50784,42306,50809,41353,39666,39713,41715,39687,43659,44304,44309,39955,50267,45741,48031,45554,45918,46830,46841,47112,47874,48496,48840,51147,51244,51415,79874,50826,48732,47833,40669,39928,40569,41209,45071,49954,50127,84019,49782,51168,51260,51410,75163,75196,75218,75707,76496,76551,76940,77139,77376,77381,77409,77410,77416,77423,77425,77426,77429,77433,77434,77861,78105,51409,40648,51075,47946,51029,45458,48499,49780,78186,78455,79382,79636,80116,80121,80233,80293,80294,80706,43942,47402,49218,43470,42191,44027,44857,45217,46647,46921,39657,9181,39423,51076,50381,50403,50463,50517,50545,42906,40331,48498,42323,40697,50019,39308,40572,39968,41611,46036,51001,41102,44635,41425,41897,42878,46548,47388,47390,39771,46303,47016,44604,44735,75752,79670,84063,83981,47053,49224,84993,41120,41207,43927,44393,41828,40123,41570,43546,43549,44088,44211,45587,39742,41576,42541,40555,41936,42546,49508,50402,44546,76301,76303,76716,76866,76870,76885,76915,76932,76981,84485,84610,84812,84069,40199,76242,83827,84301,84654,47110,76907,77082,76252,40087,84321,42489,40310,43286,40091,41109,77792,41884,42415,40880,48500,79208,40365,81639,48501,81230,81213,40747,76305,39969,50717,39987,40000,42520,39323,9184,41684,79165,79432,80773,79195,81178,84567,81089,43359,43992,44966,44326,42355,49320,43070,44390,45023,50097,78419,82805,9212,46795,76251,76273,76371,76491,76513,76872,77269,84521,84534,84569,84623,84692,84733,84770,84834,85103,85181,85203,85319,41323,50441,85212,39336,39409,40673,79948,50316"
*/


        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot create recDetailsDateIndex', $mysqli->error);
            return false;
        }else{

            if($offset==0){

                $report[] = 'recDetailsDateIndex created';
                //recreate triggers
                $res = mysql__script(HEURIST_DBNAME_FULL, 'addProceduresTriggers.sql');
                if($res!==true){
                    $system->addErrorArr($res);
                    return false;
                }

                $report[] = 'Triggers to populate recDetailsDateIndex created';

            }

            if($need_populate){

            //fill database with min/max date values
            //1. find all date values in recDetails
            $query = 'SELECT dty_ID FROM defDetailTypes WHERE dty_Type="date"';
            $fld_dates = mysql__select_list2($mysqli, $query);

            $whereDateFields = predicateId('dtl_DetailTypeID',$fld_dates);

            $query = 'SELECT count(dtl_ID) FROM recDetails '.SQL_WHERE.$whereDateFields;
            $cnt_dates = mysql__select_value($mysqli, $query);
            if($offset>0){
                $cnt_dates = $cnt_dates - $offset;
            }

            $query = 'SELECT dtl_ID,dtl_RecID,dtl_DetailTypeID,dtl_Value FROM recDetails '
            .SQL_WHERE.$whereDateFields;
            if($offset>0){
                $query = $query.' LIMIT '.$offset.', 18446744073709551615';
            }
            $res = $mysqli->query($query);

            if ($res){

                if($json_for_record_details){
                    $mysqli->query('DROP TABLE IF EXISTS bkpDetailsDateIndex'); //no used anymore
                }

                if($cnt_dates<150000){
                    $keep_autocommit = mysql__begin_transaction($mysqli);
                }

                while ($row = $res->fetch_row()){
                    $dtl_ID = intval($row[0]);
                    $dtl_RecID = intval($row[1]);
                    $dtl_DetailTypeID = intval($row[2]);
                    $dtl_Value = $row[3];
                    $dtl_NewValue = '';
                    $error = '';

                    if(trim($dtl_Value)=='') {continue;}

                    $iYear = intval($row[3]);

                    if($iYear==$dtl_Value && $iYear>0 && $iYear<10000){
                        //just year
                        $is_date_simple = true;
                        $query = 'insert into recDetailsDateIndex (rdi_RecID, rdi_DetailTypeID, rdi_DetailID, rdi_estMinDate, rdi_estMaxDate)'
." values ($dtl_RecID, $dtl_DetailTypeID, $dtl_ID, $iYear, $iYear)";
                        $res5 = $mysqli->query($query);

                        if(!$res5){
                            //fails insert into recDetailsDateIndex
                            $system->addError(HEURIST_DB_ERROR, $err_prefix.'Error on index insert query:'.$query, $mysqli->error);
                            $isok = false;
                            break;
                        }
                    }else{



            //2. Create temporal object
                    $preparedDate = new Temporal( $dtl_Value );

                    if($preparedDate && $preparedDate->isValid()){

                            // saves as usual date
                            // if date is Simple, 0<year>9999 (CE) and has both month and day
                            $is_date_simple = $preparedDate->isValidSimple();
                            $dtl_NewValue_for_update = null;
                            if($is_date_simple){
                                $dtl_NewValue = $preparedDate->getValue(true);//returns simple yyyy-mm-dd
                                $dtl_NewValue_for_update = $dtl_NewValue;
                            }else{
                                $v_json = $preparedDate->getValue();
                                $dtl_NewValue_for_update = json_encode($v_json);
                                $v_json['comment'] = '';//to avoid issue with special charss
                                $dtl_NewValue = json_encode($v_json);//$preparedDate->toJSON();//json encoded string
                            }
                            if($dtl_NewValue==null || $dtl_NewValue=='' || $dtl_NewValue=='null'){
                                $error = 'Not valid date: '.$dtl_Value;
                            }else{

            //3. Validate estMin and estMax from JSON
                            $query = 'SELECT getEstDate(\''.$dtl_NewValue
                                    .'\',0) as minD, getEstDate(\''.$dtl_NewValue.'\',1) as maxD';
                            try{
                                $res2 = $mysqli->query($query);
                            }catch(Exception $e){
                                $res2 = false;
                            }

                            if($res2){
                                $row2 = $res2->fetch_row();
                                if(($row2[0]=='' && $row2[1]=='') || ($row2[0]=='0' && $row2[1]=='0')){
                                    //fails extraction estMinDate, estMaxDate
                                    $error = 'Empty min, max dates. Min:"'.
                                        htmlspecialchars($row2[0].'" Max:"'.$row2[1]).'". Query:'.$query;
                                }else{
            //4. Keep old plain string temporal object in backup table - removed
            //5A. If simple date - retain value in recDetails
            //5B. If temporal object it saves JSON in recDetails
                                    if($dtl_Value != $dtl_NewValue_for_update){
                                        $query = 'UPDATE recDetails SET dtl_Value=? WHERE dtl_ID=?';

                                        $affected = mysql__exec_param_query($mysqli, $query,
                                                        array('si',$dtl_NewValue_for_update, $dtl_ID),true);

                                        if(!($affected>0)){
                                            //fails update recDetails  recreateRecDetailsDateIndex
                                            $system->addError(HEURIST_DB_ERROR,
                                                $err_prefix.
                                                'recreateRecDetailsDateIndex. Error on recDetails update query:'
                                                .$query.' ('.$dtl_NewValue_for_update.', '.$dtl_ID.')  ', $mysqli->error);
                                            $isok = false;
                                            break;
                                        }
                                    }


            //6. update recDetailsDateIndex should be updated by trigger
                                    $mysqli->query('delete ignore from recDetailsDateIndex where rdi_DetailID='.$dtl_ID);

                                    $mindate = floatval($row2[0]);
                                    $maxdate = floatval($row2[1]);

                                    $query = 'insert into recDetailsDateIndex (rdi_RecID, rdi_DetailTypeID, rdi_DetailID, rdi_estMinDate, rdi_estMaxDate)'
        ." values ($dtl_RecID, $dtl_DetailTypeID, $dtl_ID, $mindate, $maxdate)";
                                    $res5 = $mysqli->query($query);
        //getEstDate('$dtl_NewValue',0), getEstDate('$dtl_NewValue',1)

                                    if(!$res5){
                                        //fails insert into recDetailsDateIndex
                                        $system->addError(HEURIST_DB_ERROR, $err_prefix.'Error on index insert query:'.$query, $mysqli->error);
                                        $isok = false;
                                        break;
                                    }

                                }

                            }else{
                                //fails request
                                $error = 'Error on retrieve min and max dates. Query:'.$query.' '.$mysqli->error;
                            }

                            }
                    }else{
                        //unchange

                        //fails temporal parsing - wrong date
                        //$system->addError(HEURIST_ERROR, $err_prefix.'Cannot parse temporal "'.$dtl_Value);
                        $error = 'Cannot parse temporal';
                    }
                    }

                    //keep log
                    if(!$is_date_simple || $error){
                        // file_put_contents($log_file, $dtl_ID.';'.$dtl_Value.';'.$dtl_NewValue.';'.$error."\n", FILE_APPEND )
                        if(!$is_date_simple) {$cnt_to_json++;}
                        if($error){
                            $error = errorDiv($error);
                            $cnt_err++;
                        }

                        if($need_populate && $error){ //verbose output
                            $report[] = 'Rec# '.$dtl_RecID.'  '.htmlspecialchars($dtl_Value.' '
                                    .(($dtl_Value!=$dtl_NewValue)?$dtl_NewValue:'')).' '.$error;
                        }

                    }
                    if(!$error){
                        $cnt++;
                    }

                    $cnt_all++;

                    if($progress_report_step>=0 && $cnt_all%1000==0 ){
                        $percentage = intval($cnt_all*100/$cnt_dates);
                        if(DbUtils::setSessionVal($progress_report_step.','.$percentage)){
                            //terminated by user
                            $system->addError(HEURIST_ACTION_BLOCKED, 'Database Verification has been terminated by user');
                            if($cnt_dates<150000){
                                $mysqli->rollback();
                                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                            }
                            return false;
                        }
                    }
                }//while
                $res->close();

                if($isok){
                    if($cnt_dates<150000){
                        $mysqli->commit();
                    }
                }else{
                    if($cnt_dates<150000){
                        $mysqli->rollback();
                    }
                }
                if( $cnt_dates<150000 && $keep_autocommit===true) {$mysqli->autocommit(true);}

            }
        }
        }

        if($isok && $need_populate){ //verbose output
            $report[] = '<ul><li>Added into date index: '.$cnt.'</li>'
                        .'<li>Errors date pasring: '.$cnt_err.'</li>'
                        .'<li>Complex temporals: '.$cnt_to_json.'</li></ul>';
        }

        return $isok?$report:false;
    }


    //
    //
    //
    function trim_item(&$item, $key, $len){
        if($item!='' && $item!=null){
            $item = substr(trim($item),0,$len);
        }
    }

    //
    //
    //
    function repalce_nulls(&$item, $key){
        if($item==null){
            $item = '';
        }
    }

    //
    // for strip accents
    //
    function my_strtr($inputStr, $from, $to, $encoding = 'UTF-8') {
        $inputStrLength = mb_strlen($inputStr, $encoding);

        $translated = '';

        for($i = 0; $i < $inputStrLength; $i++) {
            $currentChar = mb_substr($inputStr, $i, 1, $encoding);

            $translatedCharPos = mb_strpos($from, $currentChar, 0, $encoding);

            if($translatedCharPos === false) {
                $translated .= $currentChar;
            }
            else {
                $translated .= mb_substr($to, $translatedCharPos, 1, $encoding);
            }
        }

        return $translated;
    }

    //
    //
    //
    function stripAccents($stripAccents){
        return my_strtr($stripAccents,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝß',
                                      'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUYs');
    }

    //
    // trim including &nbsp; and &xef; (BOM)
    //
    function super_trim( $str ){

        $str = trim($str);
        $len = strlen($str);
        $k = strpos($str,"\xC2\xA0");
        if($k===0){
            $str = substr($str,2);
            return super_trim($str);
        }elseif($k===$len-2){
            $str = substr($str,0,$len-2);
            return super_trim($str);
        }
        $k = strpos($str,"\xEF\xBB\xBF");
        if($k===0){
            $str = substr($str,3);
            return super_trim($str);
        }elseif($k===$len-3){
            $str = substr($str,0,$len-3);
            return super_trim($str);
        }

        return $str;

        //return trim($str);//trim($str, " \n\r\t\v\x00\xC2\xA0\xEF\xBB\xBF");
    }

    //
    //
    //
    function  trim_lower_accent($item){
        return mb_strtolower(stripAccents(super_trim($item)));//including &nbsp; and &xef; (BOM)
    }

    function  trim_lower_accent2(&$item, $key){
        $item = trim_lower_accent($item);
    }

    function mb_strcasecmp($str1, $str2, $encoding = null) {
        if (null === $encoding) { $encoding = mb_internal_encoding();}
        return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
    }

    function is_true($val){
        return $val===true || (is_string($val) && in_array(strtolower($val), array('y','yes','true','t','ok')));
    }
    //
    //
    //
    function escapeValues($mysqli, &$values){
        foreach($values as $idx=>$v){
            $values[$idx] = $mysqli->real_escape_string($v);
        }
    }

    //
    // $rec_IDs - may by csv string or array
    // returns array of integers
    //
    function prepareIds($ids, $can_be_zero=false){

        if($ids==null){
            return array();
        }

        if(!is_array($ids)){
            if(is_numeric($ids)){
                $ids = array($ids);
            }else{
                $ids = explode(',', $ids);
            }
        }

        $res = array();
        foreach($ids as $v){
            if (is_numeric($v) && ($v > 0 || ($can_be_zero && $v==0))){
                $res[] = intval($v);
            }
        }
        return $res;
    }

    //
    //
    //
    function prepareStrIds($ids){

        if(!is_array($ids)){
            $ids = explode(',', $ids);
        }

        $ids = array_map(function ($v) {
             return '"'.$v.'"';
        }, $ids);

        return $ids;

    }

    //
    // if $operation not null it returns empty string for empty $ids and
    //    full predictate
    //
    function predicateId($field, $ids, $operation=null)
    {
        $ids = prepareIds($ids);

        $cnt = count($ids);
        if($cnt==0){
            return isEmptyStr($operation)?SQL_FALSE:''; // (1=0) none
        }elseif($cnt==1){
            $q = '='.$ids[0];
        }elseif($cnt>1){
            $q = SQL_IN.implode(',',$ids).')';
        }

        return (!isEmptyStr($operation)?" $operation ":'').'('.$field.$q.')';
    }


    //
    // returns null if some of csv is not integer
    // otherwise returns validated string with CSV
    //
    function getCommaSepIds($value)
    {
        if(is_array($value)){
            $a = $value;
        }else{
            if(substr($value, -1) === ','){
                //remove last comma
                $value = substr($value,0,-1);
            }

            $a = explode(',', $value);
        }
        $n = array_map('intval', $a);

        if(!array_diff($a, $n)){
            if(is_array($value)){
                return implode(',', $value);
            }else{
                return $value;
            }

        }else{
            return null;
        }
    }

    //
    //
    //
    function checkMaxLength2($dtl_Value){
        $dtl_Value = trim($dtl_Value);
        $len  = strlen($dtl_Value);//number of bytes
        $len2 = mb_strlen($dtl_Value);//number of characters
        $lim = ($len-$len2<200)?64000:32000; //32768;
        if($len>$lim){   //size in bytes more than allowed limit
            return $lim;
        }else{
            return 0;
        }
    }

    //
    // check max length for TEXT field
    //
    function checkMaxLength($dty_Name, $dtl_Value){

        $lim = checkMaxLength2($dtl_Value);
        //number of bytes more than limit
        //limit: if number of bytes and chars is slightly different it takes 64KB
        // otherwise it is assumed utf and limit is 32KB
        if($lim>0){ //65535){  32768
            $lim2 = ($lim>32000)?64:32;
            return 'The data in field ('.$dty_Name
            .') exceeds the maximum size for a field of '.$lim2.'Kbytes. '
            .'Note that this does not mean '.$lim2.'K characters, '
            .'as Unicode uses multiple bytes per character.';
        }else{
            return null;
        }

    }

    //
    // returns timestamp of last update of db denitions
    //
    function getDefinitionsModTime($mysqli, $recstructure_only=false)
    {
        //CONVERT_TZ(MAX(trm_Modified), @@session.time_zone, '+00:00')
        $rst_mod = mysql__select_value($mysqli, 'SELECT CONVERT_TZ(MAX(rst_Modified), @@session.time_zone, "+00:00") FROM defRecStructure');
        if($recstructure_only){
            $last_mod = $rst_mod;
        }else{

            $rty_mod = mysql__select_value($mysqli, 'SELECT CONVERT_TZ(MAX(rty_Modified), @@session.time_zone, "+00:00") FROM defRecTypes');
            $dty_mod = mysql__select_value($mysqli, 'SELECT CONVERT_TZ(MAX(dty_Modified), @@session.time_zone, "+00:00") FROM defDetailTypes');
            $trm_mod = mysql__select_value($mysqli, 'SELECT CONVERT_TZ(MAX(trm_Modified), @@session.time_zone, "+00:00") FROM defTerms');

            $last_mod = $rst_mod > $rty_mod ? $rst_mod : $rty_mod;
            $last_mod = $last_mod > $dty_mod ? $last_mod : $dty_mod;
            $last_mod = $last_mod > $trm_mod ? $last_mod : $trm_mod;
        }

        return date_create($last_mod);
    }



    //
    //
    //
    function mysql__begin_transaction($mysqli){

        $keep_autocommit = mysql__select_value($mysqli, 'SELECT @@autocommit');
        if($keep_autocommit===true || $keep_autocommit==1){
                $mysqli->autocommit(false);
                $keep_autocommit = true;
        }else{
                $keep_autocommit = false;
        }
        if (strnatcmp(phpversion(), '5.5') >= 0) {
            $mysqli->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        }

        return $keep_autocommit;
    }

    function mysql__end_transaction($mysqli, $res, $keep_autocommit){

        if($res){
            $mysqli->commit();
        }else{
            $mysqli->rollback();
        }
        if($keep_autocommit===true) {$mysqli->autocommit(true);}
    }


    //
    // returns value of session file
    // if $value is not set, it returns current value
    //
    // now it is not database based - session values are in file
    //
    function mysql__update_progress($mysqli, $session_id, $is_init, $value){

        $session_id = intval($session_id);

        if($session_id==null || $session_id==0) {return null;}

        if(!defined('HEURIST_SCRATCH_DIR')) {return null;}

        $res = null;

        $session_file = HEURIST_SCRATCH_DIR.'session'.$session_id;
        $is_exist = file_exists($session_file);

        if($value==='REMOVE'){
            if($is_exist) {fileDelete($session_file);}
            $res = 'terminate';
        }else{
            //get
            if($is_exist) {
                $res = file_get_contents($session_file);
            }

            if($value!=null && $res!='terminate'){ //already terminated
                file_put_contents($session_file, $value);
                $res = $value;
            }
        }
        return $res;
    }


    /**
    * Validates the present of all tables in given or current database
    *
    * @param mixed $mysqli
    * @param mixed $db_name
    * @return either array of missed tables or SQL error
    */
    function hasAllTables($mysqli, $db_name=null){

        $query = '';
        if($db_name!=null){
            $query = 'FROM `'.$db_name.'`';
        }

        $list = mysql__select_list2($mysqli, "SHOW TABLES $query", 'strtolower');


        $mysql_gone_away_error = $mysqli && $mysqli->errno==2006;
        if($mysql_gone_away_error){

            return 'There is database server intermittens. '.CRITICAL_DB_ERROR_CONTACT_SYSADMIN;

        }elseif($mysqli->error){

            return $mysqli->error;

        }else{

    /*not used
    defcrosswalk,defontologies,defrelationshipconstraints,defurlprefixes,
    recthreadedcomments,sysdocumentation,syslocks,usrhyperlinkfilters,
    woot_chunkpermissions,woot_chunks,woot_recpermissions,woots,
    */

    //auto recreated
    //'reclinks'

    //recreated via upgrade
    //'recdetailsdateindex','sysdashboard','sysworkflowrules','usrrecpermissions','usrworkingsubsets'
    //

            $check_list = array(
    'defcalcfunctions','defdetailtypegroups','defdetailtypes','deffileexttomimetype',
    'defrecstructure','defrectypegroups','defrectypes','defterms','deftermslinks',
    'deftranslations','defvocabularygroups','recdetails','recforwarding','records',
    'recsimilarbutnotdupes','recuploadedfiles','sysarchive','sysidentification',
    'sysugrps','sysusrgrplinks','usrbookmarks','usrrectaglinks','usrreminders',
    'usrremindersblocklist','usrreportschedule','usrsavedsearches','usrtags',
    'recdetailsdateindex','sysdashboard','sysworkflowrules','usrrecpermissions','usrworkingsubsets'
    );

            $missed = array_diff($check_list, $list);

            return $missed;
        }
    }

    /**
    * 
    * 
    * @param mixed $system
    * @param mixed $table_name
    * @param mixed $query
    * @param mixed $recreate
    */
    function createTable($system, $table_name, $query, $recreate = false){

        $mysqli = $system->getMysqli();

        if($recreate || !hasTable($mysqli, $table_name)){

            $res = $mysqli->query('DROP TABLE IF EXISTS '.$table_name);

            $res = $mysqli->query($query);
            if(!$res){
                $msg = "Cannot create $table_name";
                $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                throw new Exception($msg);
            }
            $res = array(true, "$table_name created");
        }else{
            $res = array(false, "$table_name already exists");
        }
        return $res;
    }

    /**
    * 
    * 
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $db_name
    * @return mixed
    */
    function alterTable($system, $table_name, $field_name, $query, $modify_if_exists = false){

        $mysqli = $system->getMysqli();

        $column_exists = hasColumn($mysqli, $table_name, $field_name);

        $rep1 = 'add';
        $rep2 = 'added';

        if($column_exists && $modify_if_exists){
            $query = str_replace('ADD COLUMN','MODIFY',$query);
            if(stripos($query,' AFTER `')>0){
                $query = stristr($query,' AFTER `',true);
            }
            $column_exists = false;
            $rep1 = 'alter';
            $rep2 = 'altered';
        }

        if(!$column_exists){ //column not defined
            $res = $mysqli->query($query);
            if(!$res){
                $msg = "Can not $rep1 field $field_name to $table_name";
                $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                throw new Exception($msg);
            }
            $res = array(true, "$table_name: $field_name $rep2");
        }else{
            $res = array(false, "$table_name: $field_name already exists");
        }

        return $res;
    }

    /**
    * Returns true if table exists in database
    *
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $db_name
    */
    function hasTable($mysqli, $table_name, $db_name=null){

            $query = '';
            if($db_name!=null){
                $query = 'FROM `'.$db_name.'`';
            }

            $value = mysql__select_value($mysqli, "SHOW TABLES $query LIKE '$table_name'");
            $not_exist = ($value==null || $value=='');

            return !$not_exist;
    }

    /**
    * Returns true if column exists in given table
    *
    * @param mixed $mysqli
    * @param mixed $table_name
    * @param mixed $column_name
    * @param mixed $db_name
    */
    function hasColumn($mysqli, $table_name, $column_name, $db_name=null, $given_type=null){

        if($db_name==null){
            $db_name = '';
        }else{
            $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name); //for snyk
            $db_name = "`$db_name`.";
        }

        $query = "SHOW COLUMNS FROM $db_name`$table_name` LIKE '$column_name'";

        $res = $mysqli->query($query);
        $row_cnt = 0;
        if($res) {
            $row_cnt = $res->num_rows;

            if($row_cnt>0 && $given_type!=null){
                $row = $res->fetch_assoc();
                return $row['Type']==$given_type;
            }


            $res->close();
        }
        return $row_cnt>0;
    }


    //
    // Checks that sysUGrps.ugr_Enabled has proper set ENUM('y','n','y_no_add','y_no_delete','y_no_add_delete')
    // @todo - remove, it duplicates hasColumn
    //
    function checkUserStatusColumn($system, $db_source = ''){

        if(empty($db_source) && defined(HEURIST_DBNAME_FULL)){
            $db_source = HEURIST_DBNAME_FULL;
        }

        $mysqli = $system->getMysqli();

        // Check that sysUGrps.ugr_Enabled has y_no_add, y_no_delete, y_no_add_delete
        $validate_query = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '". $db_source ."' AND TABLE_NAME = 'sysUGrps' AND COLUMN_NAME = 'ugr_Enabled'";

        $res = $mysqli->query($validate_query);

        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot check available user permissions.<br>Please contact the Heurist team, if this persists.');
            return false;
        }

        $result = $res->fetch_row()[0];
        if(strpos($result, "'y','n','y_no_add','y_no_delete','y_no_add_delete'") === false){ // check if all values are accounted for

            // Update enum values
            $update_query = "ALTER TABLE sysUGrps MODIFY COLUMN ugr_Enabled ENUM('y','n','y_no_add','y_no_delete','y_no_add_delete')";
            $res = $mysqli->query($update_query);

            if(!$res){
                $system->addError(HEURIST_DB_ERROR, 'Unable to update user permissions column.<br>Please contact the Heurist team, if this persists.');
                return false;
            }
        }

        return true;
    }

?>