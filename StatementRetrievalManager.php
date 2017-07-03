<?php

require_once 'Logger.php';
include_once("Encryption.php");
include_once("ConnectionManager.php");
include_once("HelperClass.php");


class StatementRetrievalManager
{
    private $connManager;
    private $encryption;
    private $logger;

    public function __construct($logID)
    {
        $this->encryption = new Encryption();
        $this->logger = new Logger($logID);
    }

    /**
     * The base function class that retrieves the specified SQL statement
     * @param type $postData The post data that includes the connection details and the type of SQL statement that should be retrieved
     */
    function getSqlStatement($postData)
    {
        include_once("ConnectionManager.php");
        $helperClass = new HelperClass();
        $this->connManager = new ConnectionManager();
        $status = $this->connManager->connectToDBFromPostArray($postData);

        if ($status[RESULT] != SUCCESS)
        {
            //print $this->encryption->encrypt(json_encode($status));
            HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postData, $status);
            exit();
        }

        $query = "SHOW FIELDS FROM " . $postData["database"] . "." . $postData["table"];
        $this->logger->writeToLog("Running Query: $query");
        $result = $this->connManager->conn->query($query);

        if ($result)
        {
            if ($postData['type'] == "GetSelectAllStatementCopy" ||
                $postData['type'] == "GetSelectAllStatementEditor"
            )
            {
                $data = $this->getSelectAllStatement($postData, $result);
            }
            else
            {
                if ($postData['type'] == "GetInsertStatementCopy" ||
                    $postData['type'] == "GetInsertStatementEditor"
                )
                {
                    $fields = array();
                    $i = 0;
                    while ($myrow = $result->fetch_array())
                    {
                        $fields[$i] = $myrow['Field'];
                        $i++;
                    }
                    $data = $this->getInsertStatement($postData, $fields);
                }
                else
                {
                    if ($postData['type'] == "GetUpdateStatementCopy" ||
                        $postData['type'] == "GetUpdateStatementEditor"
                    )
                    {
                        $fields = array();
                        $i = 0;
                        while ($myrow = $result->fetch_array())
                        {
                            $fields[$i] = $myrow['Field'];
                            $i++;
                        }
                        $data = $this->getUpdateStatement($postData, $fields);
                    }
                    else
                    {
                        if ($postData['type'] == "GetDeleteStatementCopy"
                            || $postData['type'] == "GetDeleteStatementEditor"
                        )
                        {
                            $data = $this->getDeleteStatement($postData);
                        }
                        else
                        {
                            if ($postData['type'] == "GetCreateStatementCopy"
                                || $postData['type'] == "GetCreateStatementEditor"
                            )
                            {
                                $data = $this->getCreateStatement($postData);
                            }
                        }
                    }
                }
            }
            $result = $helperClass->returnSuccessArray($data);
            $result[LOCAL_TUNNEL_PORT] = $status[LOCAL_TUNNEL_PORT];
            $this->logger->writeToLog("Successfully retrieved SQL statement");
            HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postData, $result);
            //print $this->encryption->encrypt(json_encode($helperClass->returnSuccessArray($data)));
        }
        else
        {
            $mysqlError = mysqli_error($this->connManager->conn);
            $this->logger->writeToLog("Failed to retrieve SQL Statement. MySQL Error: " . $mysqlError);
            $returnSqlArray = $helperClass->returnMySQLErrorArray($mysqlError, mysqli_errno($this->connManager->conn));
            $returnSqlArray[LOCAL_TUNNEL_PORT] = $status[LOCAL_TUNNEL_PORT];
            HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postData, $returnSqlArray);
            //print $this->encryption->encrypt(json_encode($helperClass->returnMySQLErrorArray(mysqli_error($this->connManager->conn), mysqli_errno($this->connManager->conn))));
        }
    }

    /**
     * Retrieves the create statement back to the main function call
     * @param type $postData The post data containing the connction details
     * @return type Will either return the create statement or print JSON an error
     */
    private function getCreateStatement($postData)
    {
        $helperClass = new HelperClass();
        $query = "SHOW CREATE TABLE `" . $postData['database'] . "`.`" . $postData['table'] . "`";
        $this->logger->writeToLog("Running query: $query");
        $result = $this->connManager->conn->query($query);
        if ($result)
        {
            while ($myrow = $result->fetch_array())
            {
                return $myrow['Create Table'];
            }
        }
        else
        {
            $mysqlError = mysqli_error($this->connManager->conn);
            $this->logger->writeToLog("Failed to get SQL statement. MySQL Error: $mysqlError");
            print $this->encryption->encrypt(json_encode($helperClass->returnMySQLErrorArray($mysqlError, mysqli_errno($this->connManager->conn))));
            exit();
        }
    }

    /**
     * Retrieves SQL to perform a select query
     * @param type $postData The connection details
     * @param type $result Result set of fields retrieved from database
     * @return string The SELECT statement query
     */
    private function getSelectAllStatement($postData, $result)
    {
        $line = "SELECT ";
        $i = 0;
        while ($myrow = $result->fetch_array())
        {
            if ($i < mysqli_num_rows($result) -1)
            {
                $line .= "`" . $postData["table"] . "`.`" . $myrow['Field'] . "`,\n\t";
            }
            else
            {
                $line .= "`" . $postData["table"] . "`.`" . $myrow['Field'] . "`\n";
            }
            $i++;
        }
        $line .= "FROM `" . $postData['database'] . "`.`" . $postData['table'] . "`;";

        return $line;
    }

    /**
     * Retrieves the INSERT INTO statement
     * @param type $postData The connection details
     * @param type $fields The fields that will make sure up the statement
     * @return string The SQL statement
     */
    private function getInsertStatement($postData, $fields)
    {
        $line = "INSERT INTO `" . $postData['database'] . "`.`" . $postData['table'] . "`\n(";

        $fieldCount = count($fields);

        for ($i = 0; $i < $fieldCount; $i++)
        {
            if ($i < $fieldCount - 1)
            {
                $line .= "`" . $fields[$i] . "`,\n";
            }
            else
            {
                $line .= "`" . $fields[$i] . "`)\n";
            }
        }

        $line .= "VALUES\n(";
        for ($i = 0; $i < $fieldCount; $i++)
        {
            if ($i < $fieldCount - 1)
            {
                $line .= "&lt;" . $fields[$i] . "&gt;,\n";
            }
            else
            {
                $line .= "&lt;" . $fields[$i] . "&gt;);";
            }
        }
        return $line;
    }

    /**
     * Returns the update statement
     * @param type $postData The connection details
     * @param type $fields The fields that will make up the statement
     * @return string The update statement
     */
    private function getUpdateStatement($postData, $fields)
    {
        $line = "UPDATE `" . $postData['database'] . "`.`" . $postData['table'] . "`\nSET\n";

        $fieldCount = count($fields);

        for ($i = 0; $i < $fieldCount; $i++)
        {
            if ($i < $fieldCount - 1)
            {
                $line .= "`" . $fields[$i] . "` = &lt;" . $fields[$i] . "&gt;,\n";
            }
            else
            {
                $line .= "`" . $fields[$i] . "` = &lt;" . $fields[$i] . "&gt;\n";
            }
        }
        $line .= "WHERE `" . $fields[0] . "` = &lt;{expr}&gt;";
        return $line;
    }

    /**
     * Retrieves the SQL DELETE statement
     * @param type $postData The connection details
     * @return string The SQL Delete statement
     */
    private function getDeleteStatement($postData)
    {
        $line = "DELETE FROM `" . $postData['database'] . "`.`" . $postData['table'] . "`\n";
        $line .= "WHERE &lt;{where_expression}&gt;";
        return $line;
    }
}

?>
