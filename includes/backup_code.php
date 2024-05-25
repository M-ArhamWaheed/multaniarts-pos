<center>

    <?php date_default_timezone_set("Asia/Karachi"); ?>

    



<?php 

if (isset($_REQUEST['action']) AND $_REQUEST['action']=="backup") {

        # code...



    /**

     * This file contains the Backup_Database class wich performs

     * a partial or complete backup of any given MySQL database

     * @author Daniel López Azaña <daniloaz@gmail.com>

     * @version 1.0

     */





    /**

     * Define database parameters here

     */



    $settings = explode('|', file_get_contents("settings.txt"));

    define("DB_HOST", $settings[0]);

    define("DB_USER", $settings[1]);

    define("DB_PASSWORD", $settings[2]);

    define("DB_NAME",$settings[3]);

    define("BACKUP_DIR", $settings[4]); // Comment this line to use same script's directory ('.')

    define("TABLES", '*'); // Full backup

    //define("TABLES", 'table1, table2, table3'); // Partial backup

    define("CHARSET", 'utf8');

    define("GZIP_BACKUP_FILE", false);  // Set to false if you want plain SQL backup files (not gzipped)



    /**

     * The Backup_Database class

     */

    class Backup_Database {

        /**

         * Host where the database is located

         */

        var $host;



        /**

         * Username used to connect to database

         */

        var $username;



        /**

         * Password used to connect to database

         */

        var $passwd;



        /**

         * Database to backup

         */

        var $dbName;



        /**

         * Database charset

         */

        var $charset;



        /**

         * Database connection

         */

        var $conn;



        /**

         * Backup directory where backup files are stored 

         */

        var $backupDir;



        /**

         * Output backup file

         */

        var $backupFile;



        /**

         * Use gzip compression on backup file

         */

        var $gzipBackupFile;



        /**

         * Content of standard output

         */

        var $output;



        /**

         * Constructor initializes database

         */

        public function __construct($host, $username, $passwd, $dbName, $charset = 'utf8') {

            $this->host            = $host;

            $this->username        = $username;

            $this->passwd          = $passwd;

            $this->dbName          = $dbName;

            $this->charset         = $charset;

            $this->conn            = $this->initializeDatabase();

            $this->backupDir       = BACKUP_DIR ? BACKUP_DIR : '.';

            $this->backupFile      = 'backup-'.$this->dbName.'-'.date("d-M-Y_H-i-s_a", time()).'.sql';

            $this->gzipBackupFile  = defined('GZIP_BACKUP_FILE') ? GZIP_BACKUP_FILE : true;

            $this->output          = '';

        }



        protected function initializeDatabase() {

            try {

                $conn = mysqli_connect($this->host, $this->username, $this->passwd, $this->dbName);

                if (mysqli_connect_errno()) {

                    throw new Exception('ERROR connecting database: ' . mysqli_connect_error());

                    die();

                }

                if (!mysqli_set_charset($conn, $this->charset)) {

                    mysqli_query($conn, 'SET NAMES '.$this->charset);

                }

            } catch (Exception $e) {

                print_r($e->getMessage());

                die();

            }



            return $conn;

        }



        /**

         * Backup the whole database or just some tables

         * Use '*' for whole database or 'table1 table2 table3...'

         * @param string $tables

         */

        public function backupTables($tables = '*') {

            try {

                /**

                * Tables to export

                */

                if($tables == '*') {

                    $tables = array();

                    $result = mysqli_query($this->conn, 'SHOW TABLES');

                    while($row = mysqli_fetch_row($result)) {

                        $tables[] = $row[0];

                    }

                } else {

                    $tables = is_array($tables) ? $tables : explode(',', str_replace(' ', '', $tables));

                }



                // $sql = 'CREATE DATABASE IF NOT EXISTS `'.$this->dbName."`;\n\n";

                // $sql .= 'USE `'.$this->dbName."`;\n\n";

                $sql="\n";

                /**

                * Iterate tables

                */

                foreach($tables as $table) {

                    $this->obfPrint("Backing up `".$table."` table...".str_repeat('.', 50-strlen($table)), 0, 0);



                    /**

                     * CREATE TABLE

                     */

                    $sql .= 'DROP TABLE IF EXISTS `'.$table.'`;';

                    $row = mysqli_fetch_row(mysqli_query($this->conn, 'SHOW CREATE TABLE `'.$table.'`'));

                    $sql .= "\n\n".$row[1].";\n\n";



                    /**

                     * INSERT INTO

                     */



                    $row = mysqli_fetch_row(mysqli_query($this->conn, 'SELECT COUNT(*) FROM `'.$table.'`'));

                    $numRows = $row[0];



                    // Split table in batches in order to not exhaust system memory 

                    $batchSize = 1000; // Number of rows per batch

                    $numBatches = intval($numRows / $batchSize) + 1; // Number of while-loop calls to perform

                    for ($b = 1; $b <= $numBatches; $b++) {

                        

                        $query = 'SELECT * FROM `'.$table.'` LIMIT '.($b*$batchSize-$batchSize).','.$batchSize;

                        $result = mysqli_query($this->conn, $query);

                        $numFields = mysqli_num_fields($result);



                        for ($i = 0; $i < $numFields; $i++) {

                            $rowCount = 0;

                            while($row = mysqli_fetch_row($result)) {

                                $sql .= 'INSERT INTO `'.$table.'` VALUES(';

                                for($j=0; $j<$numFields; $j++) {

                                    if (isset($row[$j])) {

                                        $row[$j] = addslashes($row[$j]);

                                        $row[$j] = str_replace("\n","\\n",$row[$j]);

                                        $sql .= '"'.$row[$j].'"' ;

                                    } else {

                                        $sql.= 'NULL';

                                    }



                                    if ($j < ($numFields-1)) {

                                        $sql .= ',';

                                    }

                                }



                                $sql.= ");\n";

                            }

                        }



                        $this->saveFile($sql);

                        $sql = '';

                    }



                    $sql.="\n\n\n";



                    $this->obfPrint(" OK");

                }



                if ($this->gzipBackupFile) {

                    $this->gzipBackupFile();

                } else {

                    $this->obfPrint('Backup file succesfully saved to ' . $this->backupDir.'/'.$this->backupFile, 1, 1);
                    //$name = $this->obfPrint($this->backupFile, 1, 1);
                    

                }
                ?>
                 <a href='<?=substr($this->obfPrint( $this->backupDir.'/'.$this->backupFile),0,-3)?>' class="">Download DB</a>
<?php
            } catch (Exception $e) {

                print_r($e->getMessage());

                return false;

            }



            return true;

        }



        /**

         * Save SQL to file

         * @param string $sql

         */

        protected function saveFile(&$sql) {

            if (!$sql) return false;



            try {



                if (!file_exists($this->backupDir)) {

                    mkdir($this->backupDir, 0777, true);

                }



                file_put_contents($this->backupDir.'/'.$this->backupFile, $sql, FILE_APPEND | LOCK_EX);



            } catch (Exception $e) {

                print_r($e->getMessage());

                return false;

            }



            return true;

        }



        /*

         * Gzip backup file

         *

         * @param integer $level GZIP compression level (default: 9)

         * @return string New filename (with .gz appended) if success, or false if operation fails

         */

        protected function gzipBackupFile($level = 9) {

            if (!$this->gzipBackupFile) {

                return true;

            }



            $source = $this->backupDir . '/' . $this->backupFile;

            $dest =  $source . '.gz';



            $this->obfPrint('Gzipping backup file to ' . $dest . '... ', 1, 0);



            $mode = 'wb' . $level;

            if ($fpOut = gzopen($dest, $mode)) {

                if ($fpIn = fopen($source,'rb')) {

                    while (!feof($fpIn)) {

                        gzwrite($fpOut, fread($fpIn, 1024 * 256));

                    }

                    fclose($fpIn);

                } else {

                    return false;

                }

                gzclose($fpOut);

                if(!unlink($source)) {

                    return false;

                }

            } else {

                return false;

            }

            

            $this->obfPrint('OK');

            return $dest;

        }



        /**

         * Prints message forcing output buffer flush

         *

         */

        public function obfPrint ($msg = '', $lineBreaksBefore = 0, $lineBreaksAfter = 1) {

            if (!$msg) {

                return false;

            }



            $output = '';



            if (php_sapi_name() != "cli") {

                $lineBreak = "<br />";

            } else {

                $lineBreak = "\n";

            }



            if ($lineBreaksBefore > 0) {

                for ($i = 1; $i <= $lineBreaksBefore; $i++) {

                    $output .= $lineBreak;

                }                

            }



            $output .= $msg;



            if ($lineBreaksAfter > 0) {

                for ($i = 1; $i <= $lineBreaksAfter; $i++) {

                    $output .= $lineBreak;

                }                

            }





            // Save output for later use

            $this->output .= str_replace('<br />', '\n', $output);



            echo $output;





            if (php_sapi_name() != "cli") {

                ob_flush();

            }



            $this->output .= " ";



            flush();

        }



        /**

         * Returns full execution output

         *

         */

        public function getOutput() {

            return $this->output;

        }

    }



    /**

     * Instantiate Backup_Database and perform backup

     */



    // Report all errors

    error_reporting(E_ALL);

    // Set script max execution time

    set_time_limit(900); // 15 minutes



    if (php_sapi_name() != "cli") {

        echo '<div style="font-family: monospace;">';

    }



    $backupDatabase = new Backup_Database(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    $result = $backupDatabase->backupTables(TABLES, BACKUP_DIR) ? 'OK' : 'KO';

    $backupDatabase->obfPrint('Backup result: ' . $result, 1);



    // Use $output variable for further processing, for example to send it by email

    $output = $backupDatabase->getOutput();



        if (php_sapi_name() != "cli") {

            echo '</div>';

        }



    }

?>

<?php 

    if (isset($_REQUEST['update_settings'])) {

        # code...

        $data = $_REQUEST['server']."|".$_REQUEST['user']."|".$_REQUEST['password']."|".$_REQUEST['database']."|".$_REQUEST['directory']."|";

        if(file_put_contents("settings.txt", $data)){

            echo "<div class='alert alert-success'>Saved Succesfully</div>";

        }

    }

 ?>



<!-- Restore Code -->

<?php 

if (!empty($_REQUEST['backupfile']) AND $_REQUEST['action']=="restore") {

    # code...



        /**

         * This file contains the Restore_Database class wich performs

         * a partial or complete restoration of any given MySQL database

         * @author Daniel López Azaña <daniloaz@gmail.com>

         * @version 1.0

         */



        /**

         * Define database parameters here

         */

        $settings = explode('|', file_get_contents("settings.txt"));

            define("DB_HOST", $settings[0]);

            define("DB_USER", $settings[1]);

            define("DB_PASSWORD", $settings[2]);

            define("DB_NAME",$settings[3]);

            define("BACKUP_DIR", $settings[4]); // Comment this line to use same script's directory ('.')

        define("BACKUP_FILE", $_REQUEST['backupfile']); // Script will autodetect if backup file is gzipped based on .gz extension

        define("CHARSET", 'utf8');



        /**

         * The Restore_Database class

         */

        class Restore_Database {

            /**

             * Host where the database is located

             */

            var $host;



            /**

             * Username used to connect to database

             */

            var $username;



            /**

             * Password used to connect to database

             */

            var $passwd;



            /**

             * Database to backup

             */

            var $dbName;



            /**

             * Database charset

             */

            var $charset;



            /**

             * Database connection

             */

            var $conn;



            /**

             * Constructor initializes database

             */

            function __construct($host, $username, $passwd, $dbName, $charset = 'utf8') {

                $this->host       = $host;

                $this->username   = $username;

                $this->passwd     = $passwd;

                $this->dbName     = $dbName;

                $this->charset    = $charset;

                $this->conn       = $this->initializeDatabase();

                $this->backupDir  = BACKUP_DIR ? BACKUP_DIR : '.';

                $this->backupFile = BACKUP_FILE ? BACKUP_FILE : null;

            }



            protected function initializeDatabase() {

                try {

                    $conn = mysqli_connect($this->host, $this->username, $this->passwd, $this->dbName);

                    if (mysqli_connect_errno()) {

                        throw new Exception('ERROR connecting database: ' . mysqli_connect_error());

                        die();

                    }

                    if (!mysqli_set_charset($conn, $this->charset)) {

                        mysqli_query($conn, 'SET NAMES '.$this->charset);

                    }

                } catch (Exception $e) {

                    print_r($e->getMessage());

                    die();

                }



                return $conn;

            }



            /**

             * Backup the whole database or just some tables

             * Use '*' for whole database or 'table1 table2 table3...'

             * @param string $tables

             */

            public function restoreDb() {

                try {

                    $sql = '';

                    $multiLineComment = false;



                    $backupDir = $this->backupDir;

                    $backupFile = $this->backupFile;



                    /**

                     * Gunzip file if gzipped

                     */

                    $backupFileIsGzipped = substr($backupFile, -3, 3) == '.gz' ? true : false;

                    if ($backupFileIsGzipped) {

                        if (!$backupFile = $this->gunzipBackupFile()) {

                            throw new Exception("ERROR: couldn't gunzip backup file " . $backupDir . '/' . $backupFile);

                        }

                    }



                    /**

                    * Read backup file line by line

                    */

                    $handle = fopen($backupDir . '/' . $backupFile, "r");

                    if ($handle) {

                        while (($line = fgets($handle)) !== false) {

                            $line = ltrim(rtrim($line));

                            if (strlen($line) > 1) { // avoid blank lines

                                $lineIsComment = false;

                                if (preg_match('/^\/\*/', $line)) {

                                    $multiLineComment = true;

                                    $lineIsComment = true;

                                }

                                if ($multiLineComment or preg_match('/^\/\//', $line)) {

                                    $lineIsComment = true;

                                }

                                if (!$lineIsComment) {

                                    $sql .= $line;

                                    if (preg_match('/;$/', $line)) {

                                        // execute query

                                        if(mysqli_query($this->conn, $sql)) {

                                            if (preg_match('/^CREATE TABLE `([^`]+)`/i', $sql, $tableName)) {

                                                $this->obfPrint("Table succesfully created: `" . $tableName[1] . "`");

                                            }

                                            $sql = '';

                                        } else {

                                            throw new Exception("ERROR: SQL execution error: " . mysqli_error($this->conn));

                                        }

                                    }

                                } else if (preg_match('/\*\/$/', $line)) {

                                    $multiLineComment = false;

                                }

                            }

                        }

                        fclose($handle);

                    } else {

                        throw new Exception("ERROR: couldn't open backup file " . $backupDir . '/' . $backupFile);

                    } 

                } catch (Exception $e) {

                    print_r($e->getMessage());

                    return false;

                }



                if ($backupFileIsGzipped) {

                    unlink($backupDir . '/' . $backupFile);

                }



                return true;

            }



            /*

             * Gunzip backup file

             *

             * @return string New filename (without .gz appended and without backup directory) if success, or false if operation fails

             */

            protected function gunzipBackupFile() {

                // Raising this value may increase performance

                $bufferSize = 4096; // read 4kb at a time

                $error = false;



                $source = $this->backupDir . '/' . $this->backupFile;

                $dest = $this->backupDir . '/' . date("Ymd_His", time()) . '_' . substr($this->backupFile, 0, -3);



                $this->obfPrint('Gunzipping backup file ' . $source . '... ', 0, 0);



                // Remove $dest file if exists

                if (file_exists($dest)) {

                    if (!unlink($dest)) {

                        return false;

                    }

                }

                

                // Open gzipped and destination files in binary mode

                if (!$srcFile = gzopen($this->backupDir . '/' . $this->backupFile, 'rb')) {

                    return false;

                }

                if (!$dstFile = fopen($dest, 'wb')) {

                    return false;

                }



                while (!gzeof($srcFile)) {

                    // Read buffer-size bytes

                    // Both fwrite and gzread are binary-safe

                    if(!fwrite($dstFile, gzread($srcFile, $bufferSize))) {

                        return false;

                    }

                }



                fclose($dstFile);

                gzclose($srcFile);



                $this->obfPrint('OK', 0, 2);

                // Return backup filename excluding backup directory

                return str_replace($this->backupDir . '/', '', $dest);

            }



            /**

             * Prints message forcing output buffer flush

             *

             */

            public function obfPrint ($msg = '', $lineBreaksBefore = 0, $lineBreaksAfter = 1) {

                if (!$msg) {

                    return false;

                }



                $output = '';



                if (php_sapi_name() != "cli") {

                    $lineBreak = "<br />";

                } else {

                    $lineBreak = "\n";

                }



                if ($lineBreaksBefore > 0) {

                    for ($i = 1; $i <= $lineBreaksBefore; $i++) {

                        $output .= $lineBreak;

                    }                

                }



                $output .= $msg;



                if ($lineBreaksAfter > 0) {

                    for ($i = 1; $i <= $lineBreaksAfter; $i++) {

                        $output .= $lineBreak;

                    }                

                }



                if (php_sapi_name() == "cli") {

                    $output .= "\n";

                }



                echo $output;



                if (php_sapi_name() != "cli") {

                    ob_flush();

                }



                flush();

            }

        }



        /**

         * Instantiate Restore_Database and perform backup

         */

        // Report all errors

        error_reporting(E_ALL);

        // Set script max execution time

        set_time_limit(900); // 15 minutes



        if (php_sapi_name() != "cli") {

            echo '<div style="font-family: monospace;">';

        }



        $restoreDatabase = new Restore_Database(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

        $result = $restoreDatabase->restoreDb(BACKUP_DIR, BACKUP_FILE) ? 'OK' : 'KO';

        $restoreDatabase->obfPrint("Restoration result: ".$result, 1);

        if ($result=='OK' OR $result =="KO") {

            # code...

            redirect('index.php?nav='.$_REQUEST['nav'],2000);

        }

       



        if (php_sapi_name() != "cli") {

            echo '</div>';

            }

}//isset

?>

</center>