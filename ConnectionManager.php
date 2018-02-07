<?php
    require_once 'Logger.php';
    include_once ("Encryption.php");
    include_once 'TunnelManager.php';
    error_reporting(0);
    define ("SUCCESS", 0);
    define ("ERROR", 1);
    define ("RESULT", "result");
    define ("DATA", "data");
    define ("MESSAGE", "message");
    define ("TIME_TAKEN", "time_taken");
    define ("MYSQL_ERROR", "mysql_error");
    define ("ERROR_NO", "error_no");
    define ("QUERY", "query");
    define ("LOCAL_TUNNEL_PORT", "local_tunnel_port");
    define ("TUNNEL_STATUS", "tunnel_status");
    define ("FINGERPRINT", "fingerprint");
    define ("TUNNEL_ERROR", 4);
    class ConnectionManager
    {
        var $encryption;
        var $conn;
        var $postArray;
        var $logger;
        
        public function __construct($logID)
        {
            $this->encryption = new Encryption();
            $this->logger = new Logger($logID);
        }
        
        /**
         * Get the server status from the SHOW VARIABLES command
         * @param type $postArray The postArray that contains the connection details
         */
        function getServerStatus($postArray)
        {
            $server = $postArray['server'];
            $username = $postArray['username'];
            $password = $postArray['password'];
            $database = $postArray['database'];
            $port = $postArray['port'];
            
            $this->postArray = $postArray;
            
            $connStatus = $this->connectToDB($server, $username, $password, $database, $port, "-1", $postArray);
            
            if ($connStatus[RESULT] == ERROR)
            {
                $this->logger->writeToLog("Faield to get connect to DB. MySQL Error: " . $connStatus[MYSQL_ERROR]);
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $connStatus);
                //print $this->encryption->encrypt(json_encode($connStatus));
                exit(); //Failed to connect to DB, so stop processing
            }
            
            $query = "SHOW VARIABLES";
            $this->logger->writeToLog("Running Query: $query");
            $result = $this->conn->query($query);
            if ($result)
            {
                $varArray = array();
                while ($myrow = $result->fetch_array())
                {
                    $varArray[$myrow['Variable_name']] = $myrow['Value'];
                }
                $varArray[RESULT] = SUCCESS;
                $varArray[LOCAL_TUNNEL_PORT] = $connStatus[LOCAL_TUNNEL_PORT];
                $this->logger->writeToLog("Successfully retrieved server status");
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $varArray);
                //print $this->encryption->encrypt(json_encode($varArray));
            }
            else
            {
                $status = array();
                $status[RESULT] = ERROR;
                $status[MYSQL_ERROR] = mysqli_error($this->conn);
                $status[ERROR_NO] = mysqli_errno($this->conn);
                $status[QUERY] = $query;
                $this->logger->writeToLog("Failed to get server status. MySQL Error: " . $status[MYSQL_ERROR]);
                //print $this->encryption->encrypt(json_encode($status));
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $status);
                exit(); //MySQL Error so stop processing
            }
        }


        public function getTableData($postArray)
        {
            $server = $postArray['server'];
            $username = $postArray['username'];
            $password = $postArray['password'];
            $database = $postArray['database'];
            $port = $postArray['port'];

            $connStatus = $this->connectToDB($server, $username, $password, $database, $port, "-1", $postArray);
            if ($connStatus[RESULT] == ERROR)
            {
                //print $this->encryption->encrypt(json_encode($connStatus));
                $this->logger->writeToLog("Faield to connect to the database. " . $connStatus[MYSQL_ERROR]);
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $connStatus);
                exit(); //Failed to connect to DB, so stop processing
            }

            try
            {
                $query = "SHOW TABLES FROM $database";
                $this->logger->writeToLog("Getting tables from database: $query");
                $result = $this->conn->query($query);
                if ($result)
                {
                    $tables = array();
                    $i = 0;
                    while ($myrow = $result->fetch_array())
                    {
                        $tables[$i]["TableName"] = $myrow["Tables_in_$database"];
                        $tables[$i]["TableDescription"] = $this->getTableDescription($myrow["Tables_in_$database"]);
                        $i++;
                    }
                    if (isset($postArray["version"]) && $postArray["version"] >= "2.0.1.0")
                    {
                        $returnResult[TUNNEL_STATUS][LOCAL_TUNNEL_PORT] = $connStatus[LOCAL_TUNNEL_PORT];
                        $returnResult[RESULT] = SUCCESS;
                        $returnResult[DATA] = $tables;
                        $this->logger->writeToLog("Databases successfully retrieved");
                        HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $returnResult);
                    }
                    else
                    {
                        //$dbAndTables[RESULT] = SUCCESS;
                        $this->logger->writeToLog("Databases and tables successfully retrieved");
                        HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $returnResult);
                    }
                }
                else
                {
                    throw new Exception(mysqli_error($this->conn));
                }
            }
            catch (Exception $ex)
            {
                $status = array();
                $status[RESULT] = ERROR;
                $status[MYSQL_ERROR] = mysqli_error($this->conn);
                $status[ERROR_NO] = mysqli_errno($this->conn);
                $status[QUERY] = $query;
                $this->logger->writeToLog("Failed to get table data. MySQL Error: " . $ex->getMessage());
                //print $this->encryption->encrypt(json_encode($status));
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $status);
                exit(); //MySQL Error so stop processing
            }
        }

        private function getTableDescription($tableName)
        {
            $query = "DESC $tableName";
            $this->logger->writeToLog("Getting table description: $query");
            $result = $this->conn->query($query);
            if ($result)
            {
                $data = array();
                $i = 0;
                while ($myrow = $result->fetch_array())
                {
                    $data[$i]["Field"] = $myrow["Field"];
                    $data[$i]["Type"] = $myrow["Type"];
                    $data[$i]["Null"] = ($myrow["Null"] === "No") ? false : true;
                    $data[$i]["Default"] = $myrow["Default"];
                    $data[$i]["Extra"] = $myrow["Extra"];
                    $data[$i]["Key"] = $myrow["Key"];
                    $i++;
                }
                return $data;
            }
            else
            {
                throw new Exception(mysqli_error($this->conn));
            }
        }

        public function getDatabases($postArray)
        {
            require_once 'Logger.php';
            $server = $postArray['server'];
            $username = $postArray['username'];
            $password = $postArray['password'];
            $database = $postArray['database'];
            $port = $postArray['port'];

            $connStatus = $this->connectToDB($server, $username, $password, $database, $port, "-1", $postArray);
            $logger = new Logger();
            if ($connStatus[RESULT] == ERROR || $connStatus[RESULT] == TUNNEL_ERROR)
            {
                if (isset($postArray["version"]) && $postArray["version"] >= "2.2.0.1") {
                    //print $this->encryption->encrypt(json_encode($connStatus));
                    $this->logger->writeToLog("Faield to connect to the database. " . $connStatus[MYSQL_ERROR]);
                    HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $connStatus);
                    exit(); //Failed to connect to DB, so stop processing
                }
            }

            $databases = array();
            $query = "SELECT SCHEMA_NAME AS `Database` FROM information_schema.SCHEMATA";
            $result = $this->conn->query($query);
            if($result)
            {
                $this->logger->writeToLog("Found " . mysqli_num_rows($result) . " databases");
                while ($myrow = $result->fetch_array())
                {
                    $databases[] = $myrow["Database"];
                }
            }
            else
            {
                $status = array();
                $status[RESULT] = ERROR;
                $status[MYSQL_ERROR] = mysqli_error($this->conn);
                $status[ERROR_NO] = mysqli_errno($this->conn);
                $status[QUERY] = $query;
                $status[LOCAL_TUNNEL_PORT] = $connStatus[LOCAL_TUNNEL_PORT];
                //print $this->encryption->encrypt(json_encode($status));
                $this->logger->writeToLog("Failed to get databases. MySQL Error: " . $status[MYSQL_ERROR]);
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $status);
                exit(); //MySQL Error so stop processing
            }

            if (isset($postArray["version"]) && $postArray["version"] >= "2.0.1.0")
            {
                $returnResult[TUNNEL_STATUS][LOCAL_TUNNEL_PORT] = $connStatus[LOCAL_TUNNEL_PORT];
                $returnResult[RESULT] = SUCCESS;
                $returnResult[DATA] = $databases;
                $this->logger->writeToLog("Databases successfully retrieved");
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $returnResult);
            }
            else
            {
                //$dbAndTables[RESULT] = SUCCESS;
                $this->logger->writeToLog("Databases and tables successfully retrieved");
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $databases);
            }
        }


        /**
         * Returns a JSON array to retrieve all of the databases that are availabe on the connection
         * and calls a function to also get the tables from each database
         * @param array $postArray The post array that contains that connection details
         * @deprecated This replaced with two separate API calls GetDatabases and GetTables
         * @throws Exception
         */
        function retrieveDBsAndTables($postArray)
        {
            $server = $this->encryption->decrypt($postArray['server']);
            $username = $this->encryption->decrypt($postArray['username']);
            $password = $this->encryption->decrypt($postArray['password']);
            $database = $this->encryption->decrypt($postArray['database']);
            $port = $this->encryption->decrypt($postArray['port']);
            
            $connStatus = $this->connectToDB($server, $username, $password, $database, $port, "-1", $postArray);
            if ($connStatus[RESULT] == ERROR)
            {
                //print $this->encryption->encrypt(json_encode($connStatus));
                $this->logger->writeToLog("Faield to connect to the database. " . $connStatus[MYSQL_ERROR]);
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $connStatus);
                exit(); //Failed to connect to DB, so stop processing
            }
            
            $dbAndTables = array();
            
            if ((!isset($database)) || empty($database))
            {
                $query = "SELECT SCHEMA_NAME AS `Database` FROM information_schema.SCHEMATA";
                $result = $this->conn->query($query);
                if ($result)
                {
                    $this->logger->writeToLog("Found " . mysqli_num_rows($result) . " database(s)");
                    while ($myrow = $result->fetch_array())
                    {
                        $dbAndTables[$myrow['Database']] = $this->getTables($myrow['Database']);
                    }
                }
                else
                {
                    $status = array();
                    $status[RESULT] = ERROR;
                    $status[MYSQL_ERROR] = mysqli_error($this->conn);
                    $status[ERROR_NO] = mysqli_errno($this->conn);
                    $status[QUERY] = $query;
                    $status[LOCAL_TUNNEL_PORT] = $connStatus[LOCAL_TUNNEL_PORT];
                    //print $this->encryption->encrypt(json_encode($status));
                    $this->logger->writeToLog("Failed to get databases. MySQL Error: " . $status[MYSQL_ERROR]);
                    HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $status);
                    exit(); //MySQL Error so stop processing
                }
            }
            else
            {
                $dbAndTables[$database] = $this->getTables($database);
            }
            //$dbAndTables[RESULT] = SUCCESS;
            if (isset($postArray["version"]) && $postArray["version"] >= "2.0.1.0")
            {
                $returnResult[TUNNEL_STATUS][LOCAL_TUNNEL_PORT] = $connStatus[LOCAL_TUNNEL_PORT];
                $returnResult[RESULT] = SUCCESS;
                $returnResult[DATA] = $dbAndTables;
                $this->logger->writeToLog("Databases and tables successfully retrieved");
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $returnResult);
            }
            else
            {
                //$dbAndTables[RESULT] = SUCCESS;
                $this->logger->writeToLog("Databases and tables successfully retrieved");
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $dbAndTables);
            }
            //print $this->encryption->encrypt(json_encode($dbAndTables));
        }
        
        /**
         * Tests the passed in the connection details into the post array
         * @param type $postArray The post array that contains the connection details
         */
        function testConnection($postArray)
        {
            $tunnelManager = null;
            $localTunnelPort = "-1";
            if (isset($postArray["useTunnel"]) && $postArray["useTunnel"] === "1")
            {
                $tunnelManager = new TunnelManager();
                $tunnelManager->setSSHParametersFromPostArray($postArray);

                $this->logger->writeToLog("Testing connection to database, about to start SSH tunnel");
                $tunnelStatus = $tunnelManager->startTunnel();
                if ($tunnelStatus->result === SUCCESS)
                {
                    if ($postArray["fingerprintConfirmed"] === "false")
                    {
                        $status[RESULT] = SUCCESS;
                        $status[FINGERPRINT] = $tunnelStatus->fingerprint;
                        HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $status);
                        exit();
                    }
                    $this->localTunnelPort = $tunnelStatus->LocalTunnelPort;
                    $localTunnelPort = $tunnelStatus->LocalTunnelPort;
                    $this->logger->writeToLog("SSH tunnel was started successfully");
                }
                else
                {
                    $this->logger->writeToLog("Failed to start SSH tunnel. Error: " . $tunnelStatus->message);
                    HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $tunnelStatus);
                    exit();
                }
            }
            
            $server = $postArray['server'];
            $username = $postArray['username'];
            $password = $postArray['password'];
            $database = $postArray['database'];
            $port = intval($postArray['port']);
            
            //If we're connecting via a tunnel than connect to localhost
            if ($localTunnelPort !== "-1")
            {
                $server = "localhost"; 
            }
            $status = $this->connectToDB($server, $username, $password, $database, $port, $localTunnelPort, $postArray);

            $this->logger->writeToLog("Test completed, returning response");
            HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $status);
            //print $this->encryption->encrypt(json_encode($status));
        }
        
        /**
         * Attempts a connection to the database and returns the connection state
         * @param type $postArray The post array that contains the connection details
         * @return type a JSON encoded result of how the connection was done
         */
        function connectToDBFromPostArray($postArray)
        {
            $tunnelManager = null;
            $localTunnelPort = "-1";
            if (isset($postArray["useTunnel"]) && $postArray["useTunnel"])
            {
                $tunnelManager = new TunnelManager();
                $tunnelManager->setSSHParametersFromPostArray($postArray);
                $tunnelResponse = $tunnelManager->startTunnel();
                if(isset($tunnelResponse->LocalTunnelPort))
                {
                    $localTunnelPort = $tunnelResponse->LocalTunnelPort;
                }
                else if ($tunnelResponse->result === 4) //Need to amend this - the API Error codes should match with C++
                {
                    if (isset($postArray["version"]) && $postArray["version"] >= "2.3.0.0")
                    {
                        if ($tunnelResponse->message === "FingerprintNotMatched")
                        {
                            $status[RESULT] = TUNNEL_ERROR;
                            $status[FINGERPRINT] = $tunnelResponse->fingerprint;
                            $status[MESSAGE] = "FingerprintNotMatched";
                            return $status;
                        }
                        else if ($tunnelResponse->message === "PasswordAuthFailed")
                        {
                            $status[RESULT] = TUNNEL_ERROR;
                            $status[MESSAGE] = $tunnelResponse->message;
                            return $status;
                        }
                    }
                }
                else //If the local tunnel port not set - then the fingerprint will be returned so it can be confirmed
                {
                    if (isset($postArray["version"]) && $postArray["version"] >= "2.3.0.0") {
                        $status[RESULT] = TUNNEL_ERROR;
                        $status[FINGERPRINT] = "FingerprintNotConfirmed";
                        return $status;
                    }
                }
            }
            $server = $postArray['server'];
            $username = $postArray['username'];
            $password = $postArray['password'];
            $database = $postArray['database'];
            $port = $postArray['port'];
            if ($localTunnelPort === "-1")
            {
                return $this->connectToDB($server, $username, $password, $database, $port);
            }
            else
            {
                return $this->connectToDB($server, $username, $password, $database, $port, $localTunnelPort, $postArray);
            }
        }

        /**
         * Performs the actual MySQL Connection to the server
         * @param string $server The MySQL Server to connect to
         * @param string $username The username used for the database connection
         * @param string $password The password used for the database connection
         * @param string $database The database to use, can be null (no default database will be selected
         * @param string $port The port number that should used
         * @param string $localTunnelPort
         * @param null $postArray
         * @return array that details the connection state
         * @throws Exception
         */
        function connectToDB($server, $username, $password, $database, $port, $localTunnelPort = "-1", $postArray =  null)
        {
            require_once 'Logger.php';
            $logger = new Logger();
            //Check if the local tunnel port is -1 and then double check 
            //if the tunnel needs to be created if this function has been called directly
            $tunnelManager = null;
            $usingTunnel = false;
            $logger->writeToLog("LocalTunnelPort: $localTunnelPort");
            //If we're testing the SSH tunnel might already be started, so set usingTunnel to true, and don't start it twice
            if (!empty($localTunnelPort) && $localTunnelPort !== "-1")
            {
                $usingTunnel = true;
            }
            if (($postArray != null) && isset($postArray["useTunnel"]) && $postArray["useTunnel"] === "1" && $localTunnelPort === "-1")
            {
                $logger->writeToLog("INSIDE HERE");
                $tunnelManager = new TunnelManager();
                $tunnelManager->setSSHParametersFromPostArray($postArray);
                $tunnelResponse = $tunnelManager->startTunnel();
                if ($tunnelResponse->result === 4) //Need to ammend this - the API Error codes should match with C++
                {
                    if (isset($postArray["version"]) && $postArray["version"] >= "2.3.0.0") {
                        if ($tunnelResponse->message === "FingerprintNotMatched") {
                            $status[RESULT] = TUNNEL_ERROR;
                            $status[FINGERPRINT] = $tunnelResponse->fingerprint;
                            $status[MESSAGE] = "FingerprintNotMatched";
                            return $status;
                        } else if ($tunnelResponse->message === "PasswordAuthFailed") {
                            $status[RESULT] = TUNNEL_ERROR;
                            $status[MESSAGE] = $tunnelResponse->message;
                            return $status;
                        } else {
                            $status[RESULT] = TUNNEL_ERROR;
                            $status[MESSAGE] = $tunnelResponse->message;
                            return $status;
                        }
                    }
                }
                $usingTunnel = true;
                if ((isset($postArray["version"]) && $postArray["version"] >= "2.3.0.0") && $tunnelManager->getFingerprintConfirmed() === false)
                {
                    //Return the fingerprint
                    $status[RESULT] = SUCCESS;
                    $status[FINGERPRINT] = $tunnelResponse->fingerprint;
                    return $status;
                }
                else
                {
                    $localTunnelPort = $tunnelResponse->LocalTunnelPort;
                }
            }

            //If the local tunnel port is blank, then we the port forward wasn't set up, likely because the fingerprint was confirmed
            if ((isset($postArray["version"]) && $postArray["version"] >= "2.3.0.0") && $usingTunnel &&  empty($localTunnelPort))
            {
                $status[RESULT] = TUNNEL_ERROR;
                $status[MESSAGE] = "FingerprintNotConfirmed";
                return $status;
            }
            //Connect to the database directly
            if ($localTunnelPort === "-1")
            {
                $this->conn = mysqli_connect($server, $username, $password, null, $port);// or die (HelperClass::printResponseInCorrectEncoding(array(RESULT => ERROR, MYSQL_ERROR => mysqli_connect_error(), ERROR_NO => mysqli_connect_errno())));
            }
            //Use the tunnel
            else
            {
                $this->conn = mysqli_connect("127.0.0.1", $username, $password, null, $localTunnelPort);
            }
            if (!$this->conn)
            {
                $status[RESULT] = ERROR;
                $status[MYSQL_ERROR] = mysqli_connect_error();
                $status[ERROR_NO] = mysqli_connect_errno();
            }
            else
            {
                if (isset($database) && !empty($database))
                {
                    if (mysqli_select_db($this->conn, $database))
                    {
                        $status[RESULT] = SUCCESS;
                    }
                    else
                    {
                        $status[RESULT] = ERROR;
                        $status[MYSQL_ERROR] = mysqli_error($this->conn);
                        $status[ERROR_NO] = mysqli_errno($this->conn);
                    }
                }
                else
                {
                    $status[RESULT] = SUCCESS;
                }
            }
            if ($localTunnelPort !== "-1")
            {
                $status[LOCAL_TUNNEL_PORT] = $localTunnelPort;
            }
            return $status;
        }

        /**
         * Gets the tables from the specified database
         * @param string $db The database name where the tables should be retrieved from
         * @return array An array detailing the error if the tables couldn't be retrieved, or an array of the tables that were retrieved
         * @throws Exception
         */
        function getTables($db)
        {
            $tables = array();
            $query = "SHOW TABLES FROM $db";
            $result = $this->conn->query($query);
            if ($result)
            {
                while ($myrow = $result->fetch_array())
                {
                    $tables[] = $myrow["Tables_in_$db"];
                }
                return $tables;
            }
            else
            {
                $status = array();
                $status[RESULT] = ERROR;
                $status[MYSQL_ERROR] = mysqli_error($this->conn);
                $status[ERROR_NO] = mysqli_errno($this->conn);
                $status[QUERY] = $query;
                $status[LOCAL_TUNNEL_PORT] = $this->LocalTunnelPort;
                
                //print $this->encryption->encrypt(json_encode($status));
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($this->postArray, $status);
                exit(); //MySQL Query Failed
            }
        }
        
        /**
         * Builds an array of what the error could be
         * @param type $error The error message
         * @param type $errorCode The error code of the message
         * @return type Returns an array detailing the error
         */
        function handleError($error, $errorCode)
        {
            $status[RESULT] = ERROR;
            $status[MYSQL_ERROR] = $error;
            $status[ERROR_NO] = $errorCode;
            
            return $status;
        }
    }
