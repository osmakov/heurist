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
    * prepareStrIds
    *  predicateId - prepare field compare with one or more ids
    *
    *  checkMaxLength - check max length for TEXT field
    *  getDefinitionsModTime - returns timestamp of last update of db denitions
    *
    *  updateDatabaseToLatest - make changes in database structure according to the latest version
    *  recreateRecLinks
    *  recreateRecDetailsDateIndex
    *  hasTable - Returns true if table exists in database
    *  hasColumn - Returns true if column exists in given table
    *  checkUserStatusColumn - Checks that sysUGrps.ugr_Enabled has proper set - @todo remove
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

    /**
    * Connect to db server
    *
    * @param mixed $dbHost
    * @param mixed $dbUsername
    * @param mixed $dbPassword
    *
    * @return a MySQL link identifier on success or array with code and error message on failure.
    */
    function mysql__connection($dbHost, $dbUsername, $dbPassword, $dbPort=null){

        if(null==$dbHost || $dbHost==""){
            return array(HEURIST_SYSTEM_FATAL, "Database server is not defined. Check your configuration file");
        }

        try{
            $mysqli = mysqli_init();
            //debug mode mysqli_report(MYSQLI_REPORT_ALL);
            mysqli_report(MYSQLI_REPORT_STRICT);//MYSQLI_REPORT_ERROR |
            $mysqli->options(MYSQLI_OPT_LOCAL_INFILE, 1);
            $mysqli->real_connect($dbHost, $dbUsername, $dbPassword, null, $dbPort);

        } catch (Exception $e)  {
        }
        if(!$mysqli){
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
                $databases[] = $with_prefix ? $database : substr($database, strlen($prefix));
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
        if (!$email || !$role) {
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
                    $result[$rec_id] = $row;  //stripAccents(trim($row[1]));
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
    //print $query.'<br>';

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

        $mysqli = $system->get_mysqli();

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

        $mysqli = $system->get_mysqli();

        $dbVerSubSub = $system->get_system('sys_dbSubSubVersion');

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
                    $mysqli->query('DROP TABLE IF EXISTS bkpDetailsDateIndex');
                /*
                    $res3 = $mysqli->query('CREATE TABLE bkpDetailsDateIndex (
                         bkp_ID int unsigned NOT NULL auto_increment,
                         dtl_ID int unsigned NOT NULL,
                         dtl_Value TEXT,
                         PRIMARY KEY (bkp_ID))');
                */
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
//getEstDate('$dtl_NewValue',0), getEstDate('$dtl_NewValue',1)

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
                            $query = 'SELECT getEstDate(\''.$dtl_NewValue  //$mysqli->real_escape_string(
                                    .'\',0) as minD, getEstDate(\''.$dtl_NewValue.'\',1) as maxD';//$mysqli->real_escape_string(
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
            //4. Keep old plain string temporal object in backup table
                                    /*
                                    if($json_for_record_details && strpos($dtl_Value,'|VER=1|')===0){ // !$is_date_simple
                                        $query = 'INSERT INTO bkpDetailsDateIndex(dtl_ID,dtl_Value) VALUES('.$dtl_ID.',\''
                                            .$mysqli->real_escape_string($dtl_Value).'\')';
                                        $res4 = $mysqli->query($query);
                                        if(!$res4){
                                            $system->addError(HEURIST_DB_ERROR, $err_prefix.'Error on backup for query:'.$query, $mysqli->error);
                                            $isok = false;
                                            break;
                                        }
                                    }*/
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
                            $error = error_Div($error);
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
                                if($keep_autocommit===true) {$mysqli->autocommit(TRUE);}
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
                if( $cnt_dates<150000 && $keep_autocommit===true) {$mysqli->autocommit(TRUE);}

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

/*
    //override standard trim function to sanitize unicode white spaces
    //Rename existing function
    rename_function('trim', '__trim');
    //Override function with another
    override_function('trim', '$string', 'return override_trim($string);');

    //new trim  function
    function override_trim($string){
        $str = preg_replace('/\xc2\xa0/', ' ', $str);//non breakable space
        $str = preg_replace("/\xEF\xBB\xBF/", "", $str);// BOM
        //$str = preg_replace("/\s+/u", ' ', $str);//any spaces
        return __trim($str);
    }
*/
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
        /*
        if($len>10000){
            $stmt_size->bind_param('s', $dtl_Value);
            if($stmt_size->execute()){
                $stmt_size->bind_result($str_size);
                $stmt_size->fetch();
                if($str_size>65535){
                    $len = $str_size;
                }
            }
        }
        */
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
                $mysqli->autocommit(FALSE);
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

    //
    // For Subversion update see DBUpgrade_1.2.0_to_1.3.0.php
    //
    // This method updates from 1.3.14 to 1.3.xxxx
    //
    function updateDatabaseToLatest($system){

        $sysValues = $system->get_system(null, true);
        /*
        $dbVer = $system->get_system('sys_dbVersion');
        $dbVerSub = $system->get_system('sys_dbSubVersion');
        $dbVerSubSub = $system->get_system('sys_dbSubSubVersion');

        if($dbVer==1 && $dbVerSub==3 && $dbVerSubSub>16){

        }
        */
        return true;
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
            //$db_name = HEURIST_DBNAME_FULL;
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

    function createTable($system, $table_name, $query, $recreate = false){

        $mysqli = $system->get_mysqli();

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

    function alterTable($system, $table_name, $field_name, $query, $modify_if_exists = false){

        $mysqli = $system->get_mysqli();

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
                //$db_name = HEURIST_DBNAME_FULL;
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
                if($row['Type']==$given_type){
                    return true;
                }else{
                    return false;
                }
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

        $mysqli = $system->get_mysqli();

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