<?php

require_once 'Logger.php';
include_once("Encryption.php");
include_once("ConnectionManager.php");
include_once("HelperClass.php");

define("API_RESULTSET_LIMIT", 1000);
define("MORE_ROWS_TO_GET", "MoreRowsToGet");
define("NO_MORE_ROWS", "NoMoreRows");
define("NO_RESULT_SET_REQUIRED", "NoResultSet");
define("START_INDEX", "StartIndex");
define("ROWS_RETURNED", "RowsReturned");

class QueryExecManager
{
    var $encryption;
    var $logger;

    public function __construct($logID)
    {
        $this->encryption = new Encryption();
        $this->logger = new Logger($logID);
    }

    /**
     * Execute a query using the specified post data. The post data will include
     * the connectin details along with the query.
     * @param $postArray
     */
    function executeQuery($postArray)
    {
        include_once("ConnectionManager.php");
        $connManager = new ConnectionManager();
        $status = $connManager->connectToDBFromPostArray($postArray);
        if ($status[RESULT] !== SUCCESS)
        {
            $this->logger->writeToLog("Failed to connect to database. MySQL Error: " . $status[MYSQL_ERROR]);
            HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $status);
            exit();
        }
        if ((!isset($postArray["rowsDownloaded"])) || empty($postArray["rowsDownloaded"]))
        {
            $totalRows = 0;
        }
        else
        {
            $totalRows = $postArray["rowsDownloaded"];
        }
        $this->logger->writeToLog("$totalRows row(s) has so far been returned");
        $queryLimitedTo = null;
        if (stripos($postArray["query"], "SELECT") !== false)
        {
            //This fixes issues where WHERE parameters add slashes around the surrounding speech marks
            //quotes causing an issue with the query
            $postArray["query"] = $this->addSlahesToWhereParameters($postArray["query"], $connManager->conn);
            if ($postArray["defaultLimit"] < API_RESULTSET_LIMIT)
            {
                $queryLimitedTo = $postArray["defaultLimit"];
            }
            else
            {
                $queryLimitedTo = API_RESULTSET_LIMIT;
            }
        }

        if (!isset($postArray["startIndex"]))
        {
            $startIndex = 0;
        }
        else
        {
            $startIndex = $postArray["startIndex"];
        }


        if (stripos($postArray["query"], "SELECT") !== false)
        {
            $queryWithUpdatedLimit = $this->getLimitString($startIndex, $postArray["query"], $totalRows,
                $queryLimitedTo);
        }
        else
        {
            $queryWithUpdatedLimit = $postArray["query"];
        }
        $helperClass = new HelperClass();
        if ($status[RESULT] != SUCCESS)
        {
            HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $status);
            exit();
        }

        $this->logger->writeToLog("Executing Query: $queryWithUpdatedLimit");

        $startTime = round(microtime(true) * 1000);
        $result = $connManager->conn->query($queryWithUpdatedLimit);
        $endTime = round(microtime(true) * 1000);

        $timeDifference = $endTime - $startTime;
        $timeTaken = (double)$timeDifference / (double)1000; //Time taken in seconds
        $this->logger->writeToLog("Query took $timeTaken second(s)");

        //Set the time taken from what was previously executed
        if (isset($postArray[TIME_TAKEN]) && !empty($postArray[TIME_TAKEN]))
        {
            $timeTaken += $postArray[TIME_TAKEN];
        }

        $returnArray = array();
        if ($result && stripos($postArray["query"], "SELECT") !== false || $result && stripos($postArray["query"], "SHOW") === 0)
        {

            $data = array();
            for ($i = 0; $i < $result->field_count; $i++)
            {
                $fieldInfo = $result->fetch_field();
                //$fieldInfo = mysql_fetch_field($result, $i);
                $cols[] = $fieldInfo->name;
            }
            $data[] = $cols;

            while ($myrow = $result->fetch_row())
            {
                $data[] = $myrow;
            }

            $rowsReturned = $result->num_rows;
            $totalRows += $rowsReturned;
            //If the number of rows returned is less than the API
            //limit then there is no more data to get. Tell Android
            //so that it doesn't attempt to post it.
            if ($rowsReturned < API_RESULTSET_LIMIT)
            {
                $returnArray = $helperClass->returnSuccessArray($data);
                $returnArray[MESSAGE] = NO_MORE_ROWS;
                $returnArray[START_INDEX] = $startIndex;
                $returnArray[ROWS_RETURNED] = $totalRows;
                $returnArray[TIME_TAKEN] = $timeTaken;
            }
            else
            {
                if (($queryLimitedTo === null) || $totalRows >= $queryLimitedTo)
                {
                    $returnArray = $helperClass->returnSuccessArray($data);
                    $returnArray[MESSAGE] = NO_MORE_ROWS;
                    $returnArray[START_INDEX] = $startIndex;
                    $returnArray[ROWS_RETURNED] = $totalRows;
                    $returnArray[TIME_TAKEN] = $timeTaken;
                }
                else
                {
                    $returnArray = $helperClass->returnSuccessArray($data);
                    $returnArray[MESSAGE] = MORE_ROWS_TO_GET;
                    $returnArray[START_INDEX] = $startIndex;
                    $returnArray[ROWS_RETURNED] = $totalRows;
                    $returnArray[TIME_TAKEN] = $timeTaken;
                }
            }
            $this->logger->writeToLog("Query execution completed successfully");
            $returnArray[LOCAL_TUNNEL_PORT] = $status[LOCAL_TUNNEL_PORT];
            HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $returnArray);
        }
        else
        {
            if ($result)
            {
                $returnArray = $helperClass->returnSuccessArray($returnArray);
                $returnArray[MESSAGE] = NO_RESULT_SET_REQUIRED;
                $returnArray[LOCAL_TUNNEL_PORT] = $status[LOCAL_TUNNEL_PORT];
                $returnArray[TIME_TAKEN] = $timeTaken;
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $returnArray);
            }
            else
            {
                $helper = new HelperClass();
                $error = mysqli_error($connManager->conn);
                $errorNo = mysqli_errno($connManager->conn);
                $this->logger->writeToLog("Failed to execute MySQL Query: Error Code: $errorNo Message: $error");
                $mysqlErrorArray = $helper->returnMySQLErrorArray($error, $errorNo);
                $mysqlErrorArray[LOCAL_TUNNEL_PORT] = $status[LOCAL_TUNNEL_PORT];
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $mysqlErrorArray);
            }
        }
    }

    /**
     * This converts the limit string to get portions of the database out
     * This prevents a lock up occuring for too long and therefore result in a
     * Time out exception within Android. This ensures that if no limit string was
     * specified in the query, then add a limit string so it can get a portion
     * and post to it again to get the next portion out
     * @param int $startIndex The start index of the result set to be returned
     * (the first parameter of the limit
     * @param string $query The query to be performed
     * @return string The query where the limit string has been converted
     */
    private function getLimitString(&$startIndex, $query, $totalRows, &$returnLimitParameter)
    {
        $startIndex = intval($startIndex);
        $limitOccurrenceCount = $this->getCountOfLimitsInQuery($query);
        $this->logger->writeToLog("Limit Occurence Count: $limitOccurrenceCount");
        //Limit is in the query multiple times, therefore just return the
        //query. Likelyhood is this type of query won't be a huge amount
        //of data so shouldn't cause Android to throw an timeout exception.
        //Therefore just return the original query
        if ($limitOccurrenceCount > 1)
        {
            return $query;
        }
        //The query does include a limit, therefore,
        //ensure that the limit has both a start index and a limit
        else
        {
            if ($limitOccurrenceCount == 1)
            {
                return $this->convertLimit($query, $startIndex, $totalRows, $returnLimitParameter);
            }
            //The query doesn't include a limit, therefore,
            //just add the limit to the query
            else
            {
                //Get a portion of the data. From the start index
                //get a resultset of 10000 rows. Send this back to
                //Android and allow Android to respost if necessary
                if ($returnLimitParameter < API_RESULTSET_LIMIT)
                {
                    $returnLimitParameter = intval($returnLimitParameter);
                    $query .= " LIMIT $startIndex, $returnLimitParameter";
                }
                else
                {
                    $query .= " LIMIT $startIndex, " . API_RESULTSET_LIMIT;
                }
            }
        }


        //Get the position of the first
        return $query;
    }

    private function convertLimit($query, &$startIndex, $totalRowsCurrentlyDownloaded, &$returnLimitParameter)
    {
        $queryLength = strlen($query);
        $startOfLimit = stripos($query, "LIMIT");
        $startOfLimitParameter = $startOfLimit + 6;

        //Get the first parameter of limit, +6 to the start 5 characters in LIMIT + Space
        $limitParamater1 = str_replace(",", "",
            trim(substr($query, $startOfLimitParameter, stripos($query, " ", $startOfLimitParameter) - $queryLength)));

        $this->logger->writeToLog("Limit Parameter 1: $limitParamater1");

        $returnLimitParameter = $limitParamater1;

        //Is the next character next to the first paramater, or the 2 character after
        //the parameter is a comma, then no need to convert, just return the query
        //as it has a start index and a limit

        $queryChar1Index = $startOfLimitParameter + strlen($limitParamater1) - 1;
        $queryChar2Index = $startOfLimitParameter + strlen($limitParamater1);

        $this->logger->writeToLog("QueryChar1Index", $queryChar1Index);
        $this->logger->writeToLog("QueryChar2Index", $queryChar2Index);

        if ($query[$queryChar1Index] == ',' || $query[$queryChar2Index] == ',')
        {
            //The limit has a limit index and a start position, find out which index is the comma
            //in order to find the second paramater so this can be replaced with the API limit
            if ($query[$queryChar1Index] == ",")
            {
                $startOfLimitParam2 = $queryChar1Index;
            }
            else
            {
                $startOfLimitParam2 = $queryChar2Index;
            }
            $limitParam2 = trim(substr($query, $startOfLimitParam2 + 1, $queryLength - $startOfLimitParam2));
            $startIndex = $limitParamater1;
            $returnLimitParameter = $limitParam2;

            $this->logger->writeToLog("Limit Param 2: $limitParam2");

            $rowCountDifference = $totalRowsCurrentlyDownloaded - $limitParam2;

            if ($rowCountDifference < API_RESULTSET_LIMIT)
            {
                $newLimit = $rowCountDifference;
            }

            if ($limitParam2 > API_RESULTSET_LIMIT)
            {
                $newLimit = API_RESULTSET_LIMIT;
            }
            else
            {
                $newLimit = $limitParam2;
            }
            $startIndex = intval($startIndex);
            $newLimit = intval($newLimit);
            $limitParamater1 = intval($limitParamater1);
            $limitParam2 = intval($limitParam2);
            return str_ireplace("LIMIT $limitParamater1, $limitParam2", "LIMIT $startIndex, $newLimit", $query);
        }
        else
        {
            //Get the difference between the current downloaded rows and the users limit parameter 1
            $rowCountDifference = $totalRowsCurrentlyDownloaded - $limitParamater1;

            if ($rowCountDifference < API_RESULTSET_LIMIT)
            {
                $newLimit = $rowCountDifference;
            }

            //If the parameter of the limit is higher than the default API limit
            //then set this parameter a the API default.
            else
            {
                if ($limitParamater1 > API_RESULTSET_LIMIT)
                {
                    $newLimit = API_RESULTSET_LIMIT;
                }
                else
                {
                    $newLimit = $limitParamater1;
                }
            }
            $startIndex = intval($startIndex);
            $newLimit = intval($newLimit);
            $limitParamater1 = intval($limitParamater1);
            $query = str_ireplace("LIMIT $limitParamater1", "LIMIT $startIndex, $newLimit", $query);
            return $query;
        }
    }

    /**
     * Gets the number of times LIMIT is in the SQL query.
     * If the count is will this will be updated with a subset limit
     * to avoid timeouts, if it is greater than 2, then just return the original
     * query
     * @param string $query The query that should be checked
     * @return int The number of times the query contains LIMIT
     */
    private function getCountOfLimitsInQuery($query)
    {
        return substr_count(strtoupper($query), "LIMIT");
    }

    private function addSlahesToWhereParameters($query, $conn)
    {
        $newQuery = null;

        //Trim the query, make sure there is no blank space either side of the query.
        //Check if the last character is a semi colon and remove it. This isn't need and can
        //break the query under certain circumstances as the API changes default limits
        $query = trim($query);

        $this->logger->writeToLog("Unprocessed Query: $query");

        if ($query[strlen($query)-1] === ";")
        {
            $query[strlen($query)-1] = " ";
        }

        if (stripos($query, "WHERE") !== false)
        {
            //First check if the query has already been escaped, it should have done as that's how queries work
            //however, remove them, get the WHERE clause and get PHP to escape the parameters values

            $query = str_replace("\\'", "'", $query);
            $query = str_replace('\\"', '"', $query);

            //Get the where part of the string
            $whereQuery = substr($query, stripos($query, "WHERE"));

            //Remove the Where part of the query
            $newQuery = substr($query, 0, stripos($query, "WHERE"));

            $found = array();


            //preg_match_all('/\w+\h*=\h*\S+/', $whereQuery, $found);
            //preg_match_all('/(?:AND |OR )?\w+\h*=\h*\'([^\n\']*(?:\'\w+[^\']*|[^\n\']+|\'{2})*)\'|(?:AND |OR )?\w+\h* LIKE \h*\'([^\n\']*(?:\'\w+[^\']*|[^\n\']+|\'{2})*)\'|(?:AND |OR )?\w+\h*=\d*/', $whereQuery, $found);
            //preg_match_all('/(?:AND |OR )?\w+\h*=\h*\'([^\n\']*(?:\'\w+[^\']*|[^\n\']+|\'{2})*)\'|(?:AND |OR )?\w+\h* LIKE \h*\'([^\n\']*(?:\'\w+[^\']*|[^\n\']+|\'{2})*)\'|(?:AND |OR )?\w+\h* > \d*/', $whereQuery, $found);
            preg_match_all('/(?:AND |OR )?\w+\h*=\h*\'([^\n\']*(?:\'\w+[^\']*|[^\n\']+|\'{2})*)\'|(?:AND |OR )?\w+\h* LIKE \h*\'([^\n\']*(?:\'\w+[^\']*|[^\n\']+|\'{2})*)\'|(?:AND |OR )?\w+\h* > \d*|(?:AND |OR )?\w+\h* < \d*|(?:AND |OR )?\w+\h* = \d*|(?:AND |OR )?\w+\h* != \d*|(?:AND |OR )?\w+\h*>\d*|(?:AND |OR )?\w+\h*<\d*|\w+\h*=\d*|(?:AND |OR )?\w+\h*<>\d*|(?:AND |OR )?\w+\h*!=\d*/', $whereQuery, $found);

            //I don't know regex too well, it creates too empty array in $found, so unset index 1 and 2 and
            //index 0 contains all the where parameters
            if (isset($found[1]))
            {
                unset($found[1]);
            }
            if (isset($found[2]))
            {
                unset($found[2]);
            }

            //$found = $found[0];

            $this->logger->writeToLog("Preg Match Found: " . print_r($found, true));

            $processedWhere = "WHERE ";
            for ($i = 0; $i < count($found[0]); $i++)
            {
                if (stripos($found[0][$i], "LIKE"))
                {
                    $keyValue = explode("LIKE", $found[0][$i]);
                    $key = trim($keyValue[0]);
                    $value = trim($keyValue[1]);
                }
                else
                {
                    $keyValue = null;
                    if (strpos($found[0][$i], "=" ) > 0 && strpos($found[0][$i], "!=") === false)
                    {
                        $keyValue = explode("=", $found[0][$i]);
                        $operator = "=";
                    }
                    else if (strpos($found[0][$i], "<") > 0 && strpos($found[0][$i], "<>") === false)
                    {
                        $keyValue = explode("<", $found[0][$i]);
                        $operator = "<";
                    }
                    else if (strpos($found[0][$i], ">") > 0  && strpos($found[0][$i], "<>") === false)
                    {
                        $keyValue = explode(">", $found[0][$i]);
                        $operator = ">";
                    }
                    else if (strpos($found[0][$i], "!=") > 0)
                    {
                        $keyValue = explode("!=", $found[0][$i]);
                        $operator = "!=";
                    }
                    else if (strpos($found[0][$i], "<>") > 0)
                    {
                        $keyValue = explode("<>", $found[0][$i]);
                        $operator = "<>";
                    }
                    $key = trim($keyValue[0]);
                    $value = trim($keyValue[1]);
                }
                //Remove the surrounding quotes, they will get re-added at the end

                //Remove the surrounding quotes, they will get re-added at the end if they exist
                if ($value[0] === "'")
                {
                    $value[0] = "";
                    $value[strlen($value) - 1] = "";
                    $value = trim($value);
                    $value = mysqli_escape_string($conn, $value);
                    $processedWhere .= " $key $operator '$value' ";
                }
                else
                {
                    $value = trim($value);
                    $value = mysqli_escape_string($conn, $value);

                    $processedWhere .= " $key $operator $value ";
                }

                if (stripos($found[0][$i], "LIKE"))
                {
                    $processedWhere .= " $key LIKE '$value'";
                }
            }
            $newQuery .= " $processedWhere";
            return $newQuery;
        }
        else
        {
            //There's no where clause so return the original
            return $query;
        }
    }
}

?>
