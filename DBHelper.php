<?php
    abstract class DBTransactionStatus
    {
        const SUCCESSFUL = 0;
        const FAILURE = 1;
    }
    class DBHelper
    {
        private $dbConnection = null;
        
        function mysqlIConnectFromConfigArray($configArray)
        {
            try
            {
                $server = $configArray['database']['server'];
                $username = $configArray['database']['username'];
                $password = $configArray['database']['password'];
                $port = $configArray['database']['port'];
                $database = null;
                if (isset($configArray['database']['database']) && 
                        !empty($configArray['database']['database']))
                {
                    $database = $configArray['database']['database'];
                }

                return $this->mysqlIconnectToDB($server, $username, $password, $port, $database);
            }
            catch (Exception $e)
            {
                throw $e;
            }
        }
        
        function mysqlIconnectToDB($server, $username, $password, $port, $database)
        {
            if ($this->dbConnection !== null)
            {
                return $this->dbConnection;
            }
            $conn = mysqli_connect($server, $username, $password, null, $port);
            if (!$conn)
            {
                $error = mysqli_connect_error();
                $errorno = mysqli_connect_errno();
                throw new Exception("Unable to connect to database. Error: $error. Error No: $errorno");
            }
            
            if (isset($database) && !empty($database))
            {
                if (!mysqli_select_db($conn, $database))
                {
                    $error = mysqli_error($conn);
                    $errorno = mysqli_errno($conn);
                    throw new Exception("Unable to connect to database. Error: $error. Error No: $errorno");
                }
            }
            $this->dbConnection = $conn;
            return $conn;
        }
        
        function closeMysqlIDBConnection($conn)
        {
            if ($conn != null)
            {
                if (mysqli_close($conn) === false)
                {
                    $error = mysql_error();
                    $errorno = mysql_errno();
                    throw new Exception("Failed to close DB Connection. Error: $error. Error No: $errorno");
                }
                else
                {
                    return true;
                }
            }
        }
        
        /**
         * Connects to a database from config array, using ConfigManager class and get the array 
         * @param array $configArray Array values from the config file created from ConfigManager.php
         * @return MySQLConnection A valid MySQL Connection Resouce
         * @deprecated since version 1.0.0.5
         */
        function connectToDBFromConfigArray($configArray)
        {
            $server = $configArray['database']['server'];
            $username = $configArray['database']['username'];
            $password = $configArray['database']['password'];
            $port = $configArray['database']['port'];
            $database = null;
            if (isset($configArray['database']['database']) && 
                    !empty($configArray['database']['database']))
            {
                $database = $configArray['database']['database'];
            }
            
            return $this->connectToDB($server, $username, $password, $port, $database);
        }
        
        /**
         * Connects to a database using individual variables for username, password and server etc
         * @param String $server The server to connect to
         * @param String $username The username to connect to the server
         * @param String $password The password to connect to the server
         * @param int $port The port to connect to the server
         * @param String $database 
         * @return MySQLConnection Valid MySQL Connection resource
         * @throws Exception Thrown when failed to connect to database
         */
        function connectToDB($server, $username, $password, $port, $database)
        {
            $conn = mysql_connect($server, $username, $password, $port);
            if ($conn === false)
            {
                $error = mysql_error();
                $errorno = mysql_errno();
                throw new Exception("Unable to connect to database. Error: $error. Error No: $errorno");
            }
            
            if (isset($database) && !empty($database))
            {
                if (mysql_select_db($database) === false)
                {
                    $error = mysql_error();
                    $errorno = mysql_errno();
                    throw new Exception("Unable to connect to database. Error: $error. Error No: $errorno");
                }
            }
            
            return $conn;
        }
        
        function startDBTransaction($conn = null)
        {
            if ($conn == null)
            {
                if (!mysql_query("START TRANSACTION"))
                {
                    throw new Exception("Failed to start transaction. MySQL Error: " . mysql_error());
                }
            }
            else
            {
                if (!$conn->query("START TRANSACTION"))
                {
                    throw new Exception("Failed to start transaction. MySQL Error: " . mysql_error());
                }
            }
        }
        
        function finishDBTransaction($transStatus, $conn = null)
        {
            if ($transStatus == DBTransactionStatus::SUCCESSFUL)
            {
                if ($conn == null)
                {
                    if (!mysql_query("COMMIT"))
                    {
                        throw new Exception("Failed to commit transaction. MySQL Error: " . mysql_error());
                    }
                }
                else
                {
                    if (!$conn->query("COMMIT"))
                    {
                        throw new Exception("Failed to commit transaction. MySQL Error: " . mysql_error());
                    }
                }
            }
            else if ($transStatus == DBTransactionStatus::FAILURE)
            {
                if ($conn == null)
                {
                    if (!mysql_query("ROLLBACK"))
                    {
                        throw new Exception("Failed to rollback transaction. MySQL Error: " . mysql_error());
                    }
                }
                else
                {
                    if (!$conn->query("ROLLBACK"))
                    {
                        throw new Exception("Failed to rollback transaction. MySQL Error: " . mysql_error());
                    }
                }
            }
        }
        
        /**
         * Closes a mySQL Database Connection
         * @param MySQLConnection $conn A valid MySQL Connection
         * @return boolean true on success otherwise throws an exception
         * @throws Exception Thrown when failed to close database
         * @deprecated since version 1.0.0.5
         */
        function closeDBConnection($conn)
        {
            if ($conn != null)
            {
                if (mysql_close($conn) === false)
                {
                    $error = mysql_error();
                    $errorno = mysql_errno();
                    throw new Exception("Failed to close DB Connection. Error: $error. Error No: $errorno");
                }
                else
                {
                    return true;
                }
            }
        }
        
        /**
         * Get the ID that is going to be used next when the record is inserted
         * @param String $database The database name that should be looked in
         * @param String $table The table name where the id should be found
         * @param String $error The memory location of the error, this way error is returned to the calling function
         * @return int/String int if query is executed successfully and string for the error message on failure
         */
        function getTheNextIDFromDBToUse($database, $table, $error)
        {
            $query = "SELECT `AUTO_INCREMENT` FROM  INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = '$table';";
            $result = mysql_query($query);
            if ($result)
            {
                $myrow = mysql_fetch_array($result);
                $error = null;
                return $myrow['AUTO_INCREMENT'];
            }
            else
            {
                $error = mysql_error();
                return -1;
            }
        }
    }

?>