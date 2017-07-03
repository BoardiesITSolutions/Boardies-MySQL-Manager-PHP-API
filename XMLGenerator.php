<?php

    class XMLGenerator
    {
        private $xmlSettings;
        private $tabIndex = -1;
        private $xmlString;
        private $parentsNotEnded = "/";
        
        public function __construct($xmlSettings = null)
        {
            $this->xmlString = "";
            if ($xmlSettings == null)
            {
                $this->xmlSettings = new XMLSettings();
            }
            else
            {
                $this->xmlSettings = $xmlSettings;
            }
        }
        
        public function writeStartDocument()
        {
            $this->tabIndex = -1;
            $this->xmlString = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>";
            $this->xmlString .= $this->xmlSettings->getNewLineChars();
        }
        
        public function writeStartElement($startElement)
        {
            $this->parentsNotEnded .= $startElement . "/";
            $this->xmlString .= sprintf("%s<%s>%s", $this->getTabs(), $startElement, $this->xmlSettings->getNewLineChars());
            $this->tabIndex++;
        }
        
        public function writeElementString($attribute, $value)
        {
            $this->tabIndex++;
            $this->xmlString .= sprintf("%s<%s>%s</%s>%s", $this->getTabs(), $attribute, $value, $attribute, $this->xmlSettings->getNewLineChars());
            $this->tabIndex--;
        }
        
        public function writeXmlTagWithSingleAttribute($tagName, $attribute, $attributeValue, $value = null)
        {
            $dictionary = new Dictionary();
            $dictionary->add($attribute, $attributeValue);
            
            $this->writeXmlTagWithAttributeArray($tagName, $dictionary->getDictionary(), $value);
        }
        
        public function writeXmlTagWithAttributeArray($tagName, $attributeDictionary, $value = null)
        {
            $this->tabIndex++;
            $this->xmlString .= sprintf("%s<%s", $this->getTabs(), $tagName);
            foreach ($attributeDictionary as $attribute)
            {
                $this->xmlString .= sprintf(" %s=\"%s\"", $attribute->getKey(), $attribute->getValue());
            }
            if (empty($value))
            {
                $this->xmlString .= " />";
            }
            else
            {
                $this->xmlString .= sprintf(">%s</%s>", $value, $tagName);
            }
            $this->xmlString .= $this->xmlSettings->getNewLineChars();
            $this->tabIndex--;
        }
        
        public function writeEndElement()
        {
            $parents = explode("/", $this->parentsNotEnded);
            $parents = array_values(array_filter($parents));
            $lastElement = count($parents)-1;
            $this->xmlString .= sprintf("%s</%s>%s", $this->getTabs(), $parents[$lastElement], $this->xmlSettings->getNewLineChars());
            $this->parentsNotEnded = str_replace("/" . $parents[$lastElement] . "/", "/", $this->parentsNotEnded);
            $this->tabIndex--;
        }
        
        public function addCustomLineToXML($xmlString)
        {
            $this->xmlString .= $xmlString . $this->xmlSettings->getNewLineChars();
        }
        
        private function getTabs()
        {
            $tab = "";
            for ($i = 0; $i < $this->tabIndex; $i++)
            {
                $tab .= "\t";
            }
            return $tab;
        }
        
        public function returnXmlString()
        {
            return trim($this->xmlString);
        }
    }
    
    class XMLSettings
    {
        private $indent = false;
        private $newLineChars = "\n";
        
        public function getIndent()
        {
            return $this->indent;
        }
        
        public function setNewLineChars($newLineChar)
        {
            $this->newLineChars($newLineChar);
        }
        
        public function getNewLineChars()
        {
            return $this->newLineChars;
        }
    }

?>