<?php

    require_once "Logger.php";
    include_once("ConnectionManager.php");
    include_once("QueryExecManager.php");
    include_once("StatementRetrievalManager.php");


    define("VERSION", "2.0.0.1");
    
    if (empty($_POST))
    {
        echo "Array Empty";
        exit(0);
    }

    $logger = new Logger();
    $logID = $logger->getLogID();

    $logger->writeToLog("Received API call from " . HelperClass::getIP());

    
    $postArray = decryptPostArray($_POST);

    switch ($postArray['type'])
    {
        case "GetAPIStatus":
            $logger->writeToLog("Getting API Status");
            $helperClass = new HelperClass();

            $returnArray = $helperClass->returnSuccessArray(null);
            $returnArray["Version"] = VERSION;
            print json_encode($returnArray);
            break;
        case "TestDBConnection":
            $logger->writeToLog("About to test connection to: " . $postArray["server"]);
            $connManager = new ConnectionManager($logID);
            print $connManager->testConnection($postArray);
            //echo $encryption->encrypt("{\"result\":1,\"message\":\"there was a problem creating the tunnel\"}");
            return;
        case "RetrieveDatabasesAndTables":
            $logger->writeToLog("About to retrieve databaes and tables");
            $connManager = new ConnectionManager($logID);
            print $connManager->retrieveDBsAndTables($postArray);
            break;
        case "GetDatabases":
            $logger->writeToLog("About to retrieve databases");
            $connManager = new ConnectionManager($logID);
            print $connManager->getDatabases($postArray);
            break;
        case "GetTables":
            $logger->writeToLog("About to retrieve tables");
            $connManager = new ConnectionManager($logID);
            print $connManager->getTableData($postArray);
            break;
        case "GetServerStatus":
            $connManager = new ConnectionManager($logID);
            print $connManager->getServerStatus($postArray);
            break;
        case "ExecuteSQL":
        case "ExecuteSQLRepost":
            $logger->writeToLog("About to perform SQL Query");
            $queryExecMan = new QueryExecManager($logID);
            print $queryExecMan->executeQuery($postArray);
            break;
        case "GetSelectAllStatementCopy":
        case "GetSelectAllStatementEditor":
        case "GetInsertStatementCopy":
        case "GetInsertStatementEditor":
        case "GetUpdateStatementCopy":
        case "GetUpdateStatementEditor":
        case "GetDeleteStatementCopy":
        case "GetDeleteStatementEditor":
        case "GetCreateStatementCopy":
        case "GetCreateStatementEditor":
            $logger->writeToLog("About to retrieve SQL statement");
            $statementManager = new StatementRetrievalManager($logID);
            return $statementManager->getSqlStatement($postArray);
        default:
            $helperClass = new HelperClass();
            $returnArray = $helperClass->returnError("Unknown API Method. Type: '" . $_POST["type"] . "'");
            print json_encode($returnArray);
    }

    function decryptPostArray($postArray)
    {
        require_once 'Encryption.php';
        try
        {
            $decryptedPostArray = array();

            $encryption = new Encryption();

            foreach ($postArray as $key => $value) {
                $decryptedPostArray[$encryption->decrypt($key)] = $encryption->decrypt($value);
            }
            return $decryptedPostArray;
        }
        catch (Exception $x)
        {
            $helperClass = new HelperClass();
            $returnArray = $helperClass->returnError("DecryptionFailed");
            $returnArray["version"] = VERSION;
            print json_encode($returnArray);
        }

    }
