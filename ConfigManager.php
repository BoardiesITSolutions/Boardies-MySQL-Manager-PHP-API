<?php

class ConfigManager
{
    private $configArray = null;
    private $configFile = null;

    /**
     * Pass the name of the config file that the config manager should read from
     * @param String $configFile The name of the config file that should be read
     */
    public function __construct($configFile)
    {
        $this->configFile = $configFile;
        $this->configArray = parse_ini_file($this->configFile, true);
    }

    /**
     * Returns the array created when the config file is parsed
     * @return array The array of all values and sections from the config file
     */
    function getConfigArray()
    {
        return $this->configArray;
    }

    function getConfigArraySection($section)
    {
        return $this->configArray[$section];
    }

    /**
     * Returns the value from a config file from the section and item. If the item
     * is not found within the section of the config file, then a default value is returned
     * @param String $section The section name within the config file
     * @param String $item The item name from within the config file
     * @param String $defaultValue The default value that should be returned if the config item was not found in the section
     * @return String
     * @throws Exception
     */
    function getConfigItemValue($section, $item, $defaultValue)
    {
        //Check to ensure config array is set and not empty
        if (!isset($this->configArray) || empty($this->configArray))
        {
            throw new Exception("Config array is empty, most likely config file does not exist, or was not able to be read");
        }
        //Check if the section exists
        if (!array_key_exists($section, $this->configArray))
        {
            throw new Exception("Section not found in config file");
        }
        //Check if config item exists if not, return default value
        if (!isset($this->configArray[$section][$item]))
        {
            return $defaultValue;
        }
        else
        {
            return $this->configArray[$section][$item];
        }
    }
}