<?php

/**
 * Class Logger
 * Allows writing debug messages to a log file to help find issues with API
 * Initially call the LogManager with no paramater in the constructer, then get getLogID
 * to return a log ID. This log ID should be used through an API call from APIManager.php throughout
 * all PHP scripts so that a particular API call can be easily followed through. #
 * In subsequent script create the Logger class with this log ID as a parameter in the constructor - failing to
 * do so will result in a new log ID being generated.
 */
class Logger
{
    private $logID;
    private $configManager;
    private $logHandle;
    private $configFile = "mysql.conf";

    /**
     * Logger constructor.
     * @param int $logID On initial creation of object at start of API call this should not be passed. Get the log ID
     * by returning getLogID and then use this ID for all subsequent Logger creations.
     * @throws Exception If log file cannot be opened an exception will be thrown
     */
    public function __construct($logID = 0)
    {
        //Generate a random 5 digit number
        if ($logID === 0)
        {
            $this->logID = intval(str_pad((rand(1, 99999)), "0", STR_PAD_LEFT));
        }
        else
        {
            $this->logID = $logID;
        }

        $this->configManager = new ConfigManager($this->configFile);

        $this->logHandle = fopen($this->configManager->getConfigItemValue("debug", "debug_log", "api.log"), "a+");
        if (!$this->logHandle)
        {
            throw new Exception("Unable to open debug log file: Error: " + error_get_last());
        }
    }

    /**
     * Returns the current Log ID for this script instance
     * @return int
     */
    public function getLogID()
    {
        return $this->logID;
    }

    /**
     * Write a message to the API log file. A new line will automatically be put on the end of the log message
     * @param $logMessage The message to be written to the file
     */
    public function writeToLog($logMessage)
    {
        //Get the debug array so we can include the file name and line number of the log message - this will make debugging simple, fingers crossed
        $debug = debug_backtrace();
        $currentFile = $debug[0]["file"];
        $lineNumber = $debug[0]["line"];
        $currentDate = date("d/m/Y H:i:s");

        fwrite($this->logHandle, "$currentDate -" . $this->logID . "-: $currentFile:$lineNumber:\t$logMessage\n");
        fflush($this->logHandle); //Ensure that the log is flushed to the file

        $this->archiveIfRequired();
    }

    private function archiveIfRequired()
    {
        $fileName = $this->configManager->getConfigItemValue("debug", "debug_log", "api.log");
        $fileSize = filesize(   $fileName) / (1024 * 1024);

        $maxFileSize = $this->configManager->getConfigItemValue("debug", "maxFileSizeInMB", 5);

        if ($fileSize > $maxFileSize)
        {
            //Close the file handle first
            fclose($this->logHandle);
            rename($fileName, "$fileName" . "_" . date("YmdHis"));

            //Now open a new file
            $this->logHandle = fopen($this->configManager->getConfigItemValue("debug", "debug_log", "api.log"), "w+");
        }

        //Now check if the oldest should be removed
        $files = scandir(".");
        $archivedFiles = array();
        for ($i = 0; $i < count($files); $i++)
        {
            //If it is the API log and includes an underscore - they'll be the archived logs
            if (strpos($files[$i], $fileName) === 0 && strpos($files[$i], "_") > 0)
            {
                $archivedFiles[filemtime($files[$i])] = $files[$i];
            }
        }

        //Sort the array into descending ascending order - the oldest will always be at 0
        ksort($archivedFiles, SORT_ASC);

        $keys = array_keys($archivedFiles);
        $maxArchiveCount = $this->configManager->getConfigItemValue("debug", "maxArchiveCount", 3);

        if (count($archivedFiles) > $maxArchiveCount)
        {
            do
            {
                unlink($archivedFiles[$keys[0]]);
                unset($archivedFiles[$keys[0]]);
                $archivedFiles = array_filter($archivedFiles);
            } while (count($archivedFiles) > $maxArchiveCount);
        }

    }

    public function __destruct()
    {
        fflush($this->logHandle);
        fclose($this->logHandle);
    }

}