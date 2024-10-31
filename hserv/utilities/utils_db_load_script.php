<?php
/**
* utils_db_load_script.php: Executes SQL script. Heavily modified from bigdump.php (ozerov.de/bigdump)
*                           allowing processing of very large MySQL dump files
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


//error_reporting(E_ALL);

// BigDump ver. 0.35b from 2012-12-26
// Staggered import of an large MySQL Dump (like phpMyAdmin 2.x Dump)
// Even through the webservers with hard runtime limit and those in safe mode
// Works fine with Internet Explorer 7.0 and Firefox 2.x

// Author:       Alexey Ozerov (alexey at ozerov dot de)
//               AJAX & CSV functionalities: Krzysiek Herod (kr81uni at wp dot pl)
// Copyright:    GPL (C) 2003-2013
// More Infos:   http://www.ozerov.de/bigdump

// This program is free software; you can redistribute it and/or modify it under the
// terms of the GNU General Public License as published by the Free Software Foundation;
// either version 2 of the License, or (at your option) any later version.

// THIS SCRIPT IS PROVIDED AS IS, WITHOUT ANY WARRANTY OR GUARANTEE OF ANY KIND

// USAGE

// 1. Adjust the database configuration and charset in this file
// 2. Remove the old tables on the target database if your dump doesn't contain "DROP TABLE"
// 3. Create the working directory (e.g. dump) on your web server
// 4. Upload bigdump.php and your dump files (.sql, .gz) via FTP to the working directory
// 5. Run the bigdump.php from your browser via URL like http://www.yourdomain.com/dump/bigdump.php
// 6. BigDump can start the next import session automatically if you enable the JavaScript
// 7. Wait for the script to finish, do not close the browser window
// 8. IMPORTANT: Remove bigdump.php and your dump files from the web server

// If Timeout errors still occure you may need to adjust the $linepersession setting in this file

// LAST CHANGES

// *** First ideas about adding plugin interface
// *** Fix // delimiter bug
// *** Minor fix to avoid Undefined variable curfilename notice
// *** Handle empty delimiter setting
// *** New way to determine the upload directory
// *** Set UTF8 as default connection charset

define ('DATA_CHUNK_LENGTH',16384);// How many chars are read per time
define ('TESTMODE', false);// Set to true to process the file without actually accessing the database
//define ('VERSION','0.35b');
//define ('BIGDUMP_DIR',dirname(__FILE__));
//define ('PLUGIN_DIR',BIGDUMP_DIR.'/plugins/');
define('CLOSE_P', "</p>\n");


global $errorScriptExecution;
$error = false;
$errorScriptExecution = null;

//  HEURIST_DIR."admin/setup/dbcreate/blankDBStructure.sql"
//  HEURIST_DIR."admin/setup/dbcreate/addReferentialConstraints.sql"
//  HEURIST_DIR."admin/setup/dbcreate/addProceduresTriggers.sql"
//  HEURIST_DIR."admin/setup/dbcreate/addFunctions.sql"
//  sqlCreateRecLinks.sql and upgrade scripts from HEURIST_DIR."admin/setup/dbupgrade
//
function execute_db_script($system, $database_name_full, $script_file, $message){
    global $errorScriptExecution;

    if( db_script($database_name_full, $script_file, false) ){
        return true;
    }else{
        $system->addError(HEURIST_ERROR, $message, $errorScriptExecution);
        return false;
    }

}

// Database configuration
function db_script($db_name, $filename, $verbose = false){

    global $error, $errorScriptExecution;

    $db_server   = HEURIST_DBSERVER_NAME;
    $db_username = ADMIN_DBUSERNAME;
    $db_password = ADMIN_DBUSERPSWD;
    $db_port = HEURIST_DB_PORT;

    $err_msg1 = '<p>Query: ';
    $err_msg2 = '<p>MySQL: ';

    // Connection charset should be the same as the dump file charset (utf8, latin1, cp1251, koi8r etc.)
    // See http://dev.mysql.com/doc/refman/5.0/en/charset-charsets.html for the full list
    // Change this if you have problems with non-latin letters

    $db_connection_charset = 'utf8mb4';

    // OPTIONAL SETTINGS

    //$filename           = '';// Specify the dump filename to suppress the file selection dialog
    $ajax               = true;   // AJAX mode: import will be done without refreshing the website
    $linespersession    = 3000000;   // Lines to be executed per one import session
    $delaypersession    = 0;      // You can specify a sleep time in milliseconds after each session
    // Works only if JavaScript is activated. Use to reduce server overrun

    // CSV related settings (only if you use a CSV dump)

    $csv_insert_table   = '';// Destination table for CSV files
    $csv_preempty_table = false;  // true: delete all entries from table specified in $csv_insert_table before processing
    $csv_delimiter      = ',';// Field delimiter in CSV file
    $csv_add_quotes     = true;   // If your CSV data already have quotes around each field set it to false
    $csv_add_slashes    = true;   // If your CSV data already have slashes in front of ' and " set it to false

    // Allowed comment markers: lines starting with these strings will be ignored by BigDump

    $comment[]='#'; // Standard comment lines are dropped by default
    $comment[]='-- ';
    $comment[]='DELIMITER'; // Ignore DELIMITER switch as it's not a valid SQL statement
    // $comment[]='---';// Uncomment this line if using proprietary dump created by outdated mysqldump
    // $comment[]='CREATE DATABASE';// Uncomment this line if your dump contains create database queries in order to ignore them
    // $comment[]='/*!';// Or add your own string to leave out other proprietary things

    // Pre-queries: SQL queries to be executed at the beginning of each import session

    // $pre_query[]='SET foreign_key_checks = 0';
    // $pre_query[]='Add additional queries if you want here';

    // Default query delimiter: this character at the line end tells Bigdump where a SQL statement ends
    // Can be changed by DELIMITER statement in the dump file (normally used when defining procedures/functions)

    $delimiter = ';';

    // String quotes character

    $string_quotes = '\'';// Change to '"' if your dump file uses double qoutes for strings

    // How many lines may be considered to be one query (except text lines)

    $max_query_lines = 300;

    // Where to put the upload files into (default: bigdump folder)

    $upload_dir = dirname(__FILE__);

    // *******************************************************************************************
    // If not familiar with PHP please don't change anything below this line
    // *******************************************************************************************

    if (!$verbose || $ajax){
        ob_start();
    }

    @ini_set('auto_detect_line_endings', 'true');
    @set_time_limit(0);

    if (function_exists("date_default_timezone_set") && function_exists("date_default_timezone_get")){
        @date_default_timezone_set(@date_default_timezone_get());
    }


    // Clean and strip anything we don't want from user's input [0.27b]


    $error = false;
    $errorScriptExecution = null;
    $file  = false;

    // Check PHP version

    if (!$error && !function_exists('version_compare'))
    {
        error_echo ("<p class=\"error\">PHP version 4.1.0 is required for db script read to proceed. You have PHP ".phpversion()." installed. Sorry!</p>\n");

    }

    // Check if mysql extension is available

    if (!$error && !function_exists('mysqli_connect_error'))
    {
        error_echo ("<p class=\"error\">There is no mySQL extension available in your PHP installation. Sorry!</p>\n");
    }


    do_action ('script_runs');

    // Connect to the database, set charset and execute pre-queries

    if (!$error && !TESTMODE)
    {
        $mysqli = new mysqli($db_server,$db_username,$db_password,null,$db_port);
        if (!$mysqli){

            error_echo (
                "<p class=\"error\">Database connection failed due to ".mysqli_connect_error().P_END
                ."<p>Edit the database settings in your configuration file, or ".CONTACT_SYSADMIN.P_END);

        }else{
            $success = $mysqli->select_db($db_name);

            if (!$success)
            {
                error_echo(
                    "<p class=\"error\">Database connection failed due to ".$mysqli->error.P_END
                    ."<p>Edit the database settings in your configuration file, or ".CONTACT_SYSADMIN.".</p>\n");

            }
        }

        if (!$error && $db_connection_charset!==''){
            $mysqli->query("SET NAMES $db_connection_charset");
        }


        if (!$error && isset ($pre_query) && sizeof ($pre_query)>0)
        { reset($pre_query);
            foreach ($pre_query as $pre_query_value)
            {
                if (!$mysqli->query($pre_query_value))
                {
                    error_echo(
                        "<p class=\"error\">Error with pre-query.</p>\n"
                        .$err_msg1.trim(nl2br(htmlentities($pre_query_value))).P_END
                        .$err_msg2.$mysqli->error.P_END);
                    break;
                }
            }
        }
    }
    else
    {
        $mysqli = false;
    }

    do_action('database_connected');


    // Single file mode
    $param_start = 1;
    $param_fn = $filename;
    $param_foffset = 0;
    $param_totalqueries = 0;

    /* DISABLED snyk SSRF
    if (!$error && isset($_REQUEST["fn"]))
    {
    //    echo "<p><a href=\"".$_SERVER["PHP_SELF"]."?start=1&amp;fn=".urlencode($filename)."&amp;foffset=0&amp;totalqueries=0\">Start Import</a> from $filename into $db_name at $db_server</p>\n";
    $param_start = $_REQUEST["start"];
    $param_fn = $_REQUEST["fn"];
    $param_foffset = $_REQUEST["foffset"];
    $param_totalqueries = $_REQUEST["totalqueries"];
    }
    */

    // Open the file

    if (!$error && isset($param_start))
    {

        // Set current filename ($filename overrides $param_fn if set)

        if ($filename!=""){
            $curfilename=$filename;
        }elseif (isset($param_fn)){
            $curfilename=urldecode($param_fn);
        }else{
            $curfilename="";
        }
        // Recognize GZip filename
        $gzipmode=false;

        if ((!$gzipmode && !$file=@fopen($curfilename,"r")) || ($gzipmode && !$file=@gzopen($curfilename,"r")))   //$upload_dir.'/'.
        {
            error_echo(
                "<p class=\"error\">Can't open sql script file ".$curfilename."</p>");
            /*
            ."<p>Please, check that your script file name contains only alphanumerical characters, and rename it accordingly, for example: $curfilename.".
            "<br>Or, specify \$filename in bigdump.php with the full filename. ".
            "<br>Or, you have to upload the $curfilename to the server first.</p>\n");
            */
        }
        // Get the file size (can't do it fast on gzipped files, no idea how)

        elseif ((!$gzipmode && @fseek($file, 0, SEEK_END)==0) || ($gzipmode && @gzseek($file, 0)==0))
        { if (!$gzipmode) {$filesize = ftell($file);}
            else {$filesize = gztell($file);} // Always zero, ignore
        }
        else
        {
            error_echo ("<p class=\"error\">Can't open sql script file $curfilename</p>\n");
        }

        // Stop if csv file is used, but $csv_insert_table is not set

        if (!$error && ($csv_insert_table == "") && (preg_match("/(\.csv)$/i",$curfilename)))
        {
            error_echo ("<p class=\"error\">You have to specify \$csv_insert_table when using a CSV file. </p>\n");
        }
    }

    // *******************************************************************************************
    // START IMPORT SESSION HERE
    // *******************************************************************************************

    if (!$error && isset($param_start) && isset($param_foffset) && preg_match("/(\.(sql|gz|csv))$/i",$curfilename))
    {

        do_action('session_start');

        // Check start and foffset are numeric values

        if (!is_numeric($param_start) || !is_numeric($param_foffset))
        { error_echo ("<p class=\"error\">UNEXPECTED: Non-numeric values for start and foffset</p>\n");
        }
        else
        {
            $param_start   = floor($param_start);
            $param_foffset = floor($param_foffset);
        }

        // Set the current delimiter if defined

        if (isset($_REQUEST["delimiter"])){
            $delimiter = $_REQUEST["delimiter"];
        }
        // Empty CSV table if requested

        if (!$error && $param_start==1 && $csv_insert_table != "" && $csv_preempty_table)
        {
            $query = "DELETE FROM `$csv_insert_table`";
            if (!TESTMODE && !$mysqli->query(trim($query)))
            {
                error_echo ("<p class=\"error\">Error when deleting entries from $csv_insert_table.</p>\n"
                    .$err_msg1.trim(nl2br(htmlentities($query))).P_END
                    .$err_msg2.$mysqli->error.P_END);
            }
        }

        // Print start message

        if (!$error && TESTMODE)
        {
            skin_open();
            echo "<p class=\"centr\">TEST MODE ENABLED</p>\n";
            echo "<p class=\"centr\">Processing file: <b>".$curfilename."</b></p>\n";
            echo "<p class=\"smlcentr\">Starting from line: ".$param_start.P_END;
            skin_close();
        }

        // Check $param_foffset upon $filesize (can't do it on gzipped files)

        if (!$error && !$gzipmode && $param_foffset>$filesize)
        { error_echo ("<p class=\"error\">UNEXPECTED: Can't set file pointer behind the end of file</p>\n");
        }

        // Set file pointer to $param_foffset

        if (!$error && ((!$gzipmode && fseek($file, $param_foffset)!=0) || ($gzipmode && gzseek($file, $param_foffset)!=0)))
        { error_echo ("<p class=\"error\">UNEXPECTED: Can't set file pointer to offset: ".$param_foffset.P_END);
        }

        // Start processing queries from $file

        if (!$error)
        { $query="";
            $queries = 0;
            $totalqueries = $param_totalqueries;
            $linenumber = $param_start;
            $querylines = 0;
            $inparents = false;

            // Stay processing as long as the $linespersession is not reached or the query is still incomplete

            while ($linenumber<$param_start+$linespersession || $query!="")
            {

                // Read the whole next line

                $dumpline = "";
                while (!feof($file) && substr ($dumpline, -1) != "\n" && substr ($dumpline, -1) != "\r")
                {
                    if (!$gzipmode){
                        $dumpline .= fgets($file, DATA_CHUNK_LENGTH);
                    }else{
                        $dumpline .= gzgets($file, DATA_CHUNK_LENGTH);
                    }
                }
                if ($dumpline==="") {break;}

                // Remove UTF8 Byte Order Mark at the file beginning if any

                if ($param_foffset==0){
                    $dumpline=preg_replace('|^\xEF\xBB\xBF|','',$dumpline);
                }

                // Create an SQL query from CSV line

                if (($csv_insert_table != "") && (preg_match("/(\.csv)$/i",$curfilename)))
                {
                    if ($csv_add_slashes){
                        $dumpline = addslashes($dumpline);
                    }
                    $dumpline = explode($csv_delimiter,$dumpline);
                    if ($csv_add_quotes){
                        $dumpline = "'".implode("','",$dumpline)."'";
                    }else{
                        $dumpline = implode(",",$dumpline);
                    }
                    $dumpline = 'INSERT INTO '.$csv_insert_table.' VALUES ('.$dumpline.');';
                }

                // Handle DOS and Mac encoded linebreaks (I don't know if it really works on Win32 or Mac Servers)

                $dumpline=str_replace("\r\n", "\n", $dumpline);
                $dumpline=str_replace("\r", "\n", $dumpline);

                // DIAGNOSTIC
                // echo "<p>Line $linenumber: $dumpline</p>\n";

                // Recognize delimiter statement

                if (!$inparents && strpos ($dumpline, "DELIMITER ") === 0){
                    $delimiter = str_replace ("DELIMITER ","",trim($dumpline));
                }

                // Skip comments and blank lines only if NOT in parents

                if (!$inparents)
                { $skipline=false;
                    reset($comment);
                    foreach ($comment as $comment_value)
                    {

                        // DIAGNOSTIC
                        //          echo $comment_value;
                        if (trim($dumpline)=="" || strpos (trim($dumpline), $comment_value) === 0)
                        { $skipline=true;
                            break;
                        }
                    }
                    if ($skipline)
                    { $linenumber++;

                        // DIAGNOSTIC
                        // echo "<p>Comment line skipped</p>\n";

                        continue;
                    }
                }

                // Remove double back-slashes from the dumpline prior to count the quotes ('\\' can only be within strings)

                $dumpline_deslashed = str_replace ("\\\\","",$dumpline);

                // Count ' and \' (or " and \") in the dumpline to avoid query break within a text field ending by $delimiter

                $parents=substr_count ($dumpline_deslashed, $string_quotes)-substr_count ($dumpline_deslashed, "\\$string_quotes");
                if ($parents % 2 != 0){
                    $inparents=!$inparents;
                }

                // Add the line to query

                $query .= $dumpline;

                // Don't count the line if in parents (text fields may include unlimited linebreaks)

                if (!$inparents){
                    $querylines++;
                }

                // Stop if query contains more lines as defined by $max_query_lines

                if ($querylines>$max_query_lines)
                {
                    error_echo ("<p class=\"error\">Stopped at the line $linenumber. </p>"
                        ."<p>At this place the current query includes more than ".$max_query_lines." dump lines. That can happen if your dump file was "
                        ."created by some tool which doesn't place a semicolon followed by a linebreak at the end of each query, or if your dump contains "
                        ."extended inserts or very long procedure definitions.</p>\n");
                    break;
                }

                // Execute query if end of query detected ($delimiter as last character) AND NOT in parents

                // DIAGNOSTIC
                // echo "<p>Regex: ".'/'.preg_quote($delimiter).'$/'.P_END;
                // echo "<p>In Parents: ".($inparents?"true":"false").P_END;
                // echo "<p>Line: $dumpline</p>\n";

                if ((preg_match('/'.preg_quote($delimiter,'/').'$/',trim($dumpline)) || $delimiter=='') && !$inparents)
                {

                    // Cut off delimiter of the end of the query

                    $len = intval(-1*strlen($delimiter));
                    $query = substr(trim($query),0,$len);

                    if (!$mysqli->query($query)) //!TESTMODE &&
                    {
                        $errorMsg = $mysqli->error;

                        if(strpos($errorMsg,'Cannot get geometry object')!==false){
                            error_log( $linenumber.'   '. trim($dumpline) );
                        }else{
                            error_echo ("<p class=\"error\">Error at the line $linenumber: ". trim($dumpline).P_END
                                .$err_msg1.trim(nl2br(htmlentities($query))).P_END
                                .$err_msg2.$errorMsg.P_END);
                            //ART:why it was reset? $error = false;
                            if(strpos($errorMsg,'Duplicate column')===false){
                                $error = true;
                            }
                            break;
                        }
                    }
                    $totalqueries++;
                    $queries++;
                    $query="";
                    $querylines=0;
                }
                $linenumber++;
            }
        }

        // Get the current file position

        if (!$error)
        {
            if (!$gzipmode){
                $foffset = ftell($file);
            }else{
                $foffset = gztell($file);
            }
            if (!$foffset)
            {
                error_echo ("<p class=\"error\">UNEXPECTED: Can't read the file pointer offset</p>\n");
            }
        }

        // Print statistics
        if (TESTMODE){

            skin_open();

            // echo "<p class=\"centr\"><b>Statistics</b></p>\n";

            if (!$error)
            {
                $lines_this   = $linenumber-$param_start;
                $lines_done   = $linenumber-1;
                $lines_togo   = ' ? ';
                $lines_tota   = ' ? ';

                $queries_this = $queries;
                $queries_done = $totalqueries;
                $queries_togo = ' ? ';
                $queries_tota = ' ? ';

                $bytes_this   = $foffset-$param_foffset;
                $bytes_done   = $foffset;
                $kbytes_this  = round($bytes_this/1024,2);
                $kbytes_done  = round($bytes_done/1024,2);
                $mbytes_this  = round($kbytes_this/1024,2);
                $mbytes_done  = round($kbytes_done/1024,2);

                $filesize = intval($filesize);

                if (!$gzipmode)
                {
                    $bytes_togo  = intval($filesize-$foffset);
                    $bytes_tota  = intval($filesize);
                    $kbytes_togo = round($bytes_togo/1024,2);
                    $kbytes_tota = round($bytes_tota/1024,2);
                    $mbytes_togo = round($kbytes_togo/1024,2);
                    $mbytes_tota = round($kbytes_tota/1024,2);

                    $pct_this   = ceil($bytes_this/$filesize*100);
                    $pct_done   = ceil($foffset/$filesize*100);
                    $pct_togo   = 100 - $pct_done;
                    $pct_tota   = 100;

                    if ($bytes_togo==0)
                    { $lines_togo   = '0';
                        $lines_tota   = $linenumber-1;
                        $queries_togo = '0';
                        $queries_tota = $totalqueries;
                    }

                    $pct_bar    = "<div style=\"height:15px;width:$pct_done%;background-color:#000080;margin:0px;\"></div>";
                }
                else
                {
                    $bytes_togo  = ' ? ';
                    $bytes_tota  = ' ? ';
                    $kbytes_togo = ' ? ';
                    $kbytes_tota = ' ? ';
                    $mbytes_togo = ' ? ';
                    $mbytes_tota = ' ? ';

                    $pct_this    = ' ? ';
                    $pct_done    = ' ? ';
                    $pct_togo    = ' ? ';
                    $pct_tota    = 100;
                    $pct_bar     = str_replace(' ','&nbsp;','<tt>[         Not available for gzipped files          ]</tt>');
                }

                echo "
                <center>
                <table width=\"520\" border=\"0\" cellpadding=\"3\" cellspacing=\"1\">
                <tr><th class=\"bg4\"> </th><th class=\"bg4\">Session</th><th class=\"bg4\">Done</th><th class=\"bg4\">To go</th><th class=\"bg4\">Total</th></tr>
                <tr><th class=\"bg4\">Lines</th><td class=\"bg3\">$lines_this</td><td class=\"bg3\">$lines_done</td><td class=\"bg3\">$lines_togo</td><td class=\"bg3\">$lines_tota</td></tr>
                <tr><th class=\"bg4\">Queries</th><td class=\"bg3\">$queries_this</td><td class=\"bg3\">$queries_done</td><td class=\"bg3\">$queries_togo</td><td class=\"bg3\">$queries_tota</td></tr>
                <tr><th class=\"bg4\">Bytes</th><td class=\"bg3\">$bytes_this</td><td class=\"bg3\">$bytes_done</td><td class=\"bg3\">$bytes_togo</td><td class=\"bg3\">$bytes_tota</td></tr>
                <tr><th class=\"bg4\">KB</th><td class=\"bg3\">$kbytes_this</td><td class=\"bg3\">$kbytes_done</td><td class=\"bg3\">$kbytes_togo</td><td class=\"bg3\">$kbytes_tota</td></tr>
                <tr><th class=\"bg4\">MB</th><td class=\"bg3\">$mbytes_this</td><td class=\"bg3\">$mbytes_done</td><td class=\"bg3\">$mbytes_togo</td><td class=\"bg3\">$mbytes_tota</td></tr>
                <tr><th class=\"bg4\">%</th><td class=\"bg3\">$pct_this</td><td class=\"bg3\">$pct_done</td><td class=\"bg3\">$pct_togo</td><td class=\"bg3\">$pct_tota</td></tr>
                <tr><th class=\"bg4\">% bar</th><td class=\"bgpctbar\" colspan=\"4\">$pct_bar</td></tr>
                </table>
                </center>
                \n";

                // Finish message and restart the script
                $script_name = urlencode($_SERVER["PHP_SELF"]);

                if ($linenumber<$param_start+$linespersession)
                { echo "<p class=\"successcentr\">Congratulations: End of file reached, assuming OK</p>\n";

                    do_action('script_finished');
                    $error=true; // This is a semi-error telling the script is finished
                }
                else
                {
                    if ($delaypersession!=0){
                        echo "<p class=\"centr\">Now I'm <b>waiting $delaypersession milliseconds</b> before starting next session...</p>\n";
                    }
                    if (!$ajax){
                        echo "<script language=\"JavaScript\" type=\"text/javascript\">window.setTimeout('location.href=\"".$script_name."?start=$linenumber&fn=".urlencode($curfilename)."&foffset=$foffset&totalqueries=$totalqueries&delimiter=".urlencode($delimiter)."\";',500+$delaypersession);</script>\n";
                    }

                    echo "<noscript>\n";
                    echo "<p class=\"centr\"><a href=\"".$script_name."?start=$linenumber&amp;fn=".urlencode($curfilename)."&amp;foffset=$foffset&amp;totalqueries=$totalqueries&amp;delimiter=".urlencode($delimiter)."\">Continue from the line $linenumber</a> (Enable JavaScript to do it automatically)</p>\n";
                    echo "</noscript>\n";

                    echo "<p class=\"centr\">Press <b><a href=\"".$script_name."\">STOP</a></b> to abort the import <b>OR WAIT!</b></p>\n";
                }
            }
            else
                {echo "<p class=\"error\">Stopped on error</p>\n";}

            skin_close();

        }  //end TESTMODE statistics

    }


    if ($error && TESTMODE){
        echo "<p class=\"centr\"><a href=\"".$script_name."\">Start from the beginning</a> (DROP the old tables before restarting)</p>\n";
    }
    if ($mysqli) {$mysqli->close();}
    if ($file && !$gzipmode) {fclose($file);}
    elseif($file && $gzipmode) {gzclose($file);}

    // If error or finished put out the whole output from above and stop

    if ($verbose) //ART $error &&
    {
        $out1 = ob_get_contents();
        ob_end_clean();
        echo $out1;
    }else{
        ob_clean();
    }


    // Anyway put out the output from above
    //ob_flush();

    return !$error;
}//END MAIN FUNCTIONS

// THE MAIN SCRIPT ENDS HERE

// *******************************************************************************************
// Plugin handling (EXPERIMENTAL)
// *******************************************************************************************

function do_action($tag)
{
    global $plugin_actions;

    if (isset($plugin_actions[$tag]))
    {
        reset ($plugin_actions[$tag]);
        foreach ($plugin_actions[$tag] as $action){
            call_user_func_array($action, array());
        }
    }
}

function add_action($tag, $function)
{
    global $plugin_actions;
    $plugin_actions[$tag][] = $function;
}

function skin_open()
{
    echo '<div class="skin1">';
}

function skin_close()
{
    echo DIV_E;
}

function error_echo($msg){
    global $error, $errorScriptExecution;

    error_log($msg);

    $error = true;

    $errorScriptExecution = $msg;
    //echo ($msg);
}
