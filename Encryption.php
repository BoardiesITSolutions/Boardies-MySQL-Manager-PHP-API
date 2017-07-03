<?php

/*
 * Encrypts and decrypts the given string into an AES string
 */

    require_once 'ConfigManager.php';

    class Encryption
    {
        private $cipher = null;
        private $iv = null;
        private $previousCipher = null;
        private $previousIV = null;
        public function __construct()
        {
            $configManager = new ConfigManager("mysql.conf");
            $this->cipher = $configManager->getConfigItemValue("encryption", "cipher", null);
            if ($this->cipher == null || empty($this->cipher))
            {
                throw new Exception("Encryption cipher cannot be null. Ensure that it is specified in a section call encryption in the configuration file");
            }
            $this->iv = $configManager->getConfigItemValue("encryption", "iv", null);
            if ($this->iv == null || empty($this->iv))
            {
                throw new Exception("Encryption iv cannot be null. Ensure that it is specified in a sectioned called encryption in the configuration file");
            }
        }

        function resetKeysToDefaultKeys()
        {

        }

        function encrypt($data)
        {

            //$iv = "ryojvlzmdalyglrj";
            //$key = CIPHERKEY;

            return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->cipher, $this->addpadding($data), MCRYPT_MODE_CBC, $this->iv));
        }

        private function addpadding($string, $blocksize = 16)
        {
            $len = strlen($string);
            $pad = $blocksize - ($len % $blocksize);
            $string .= str_repeat(chr($pad), $pad);

            return $string;
        }

        private function strippadding($string)
        {
            $slast = ord(substr($string, -1));
            $slastc = chr($slast);
            $pcheck = substr($string, -$slast);
            if(@preg_match("/$slastc{".$slast."}/", $string)){
                $string = substr($string, 0, strlen($string)-$slast);
                return $string;
            } else {
                throw new Exception("Strip padding failed. Likely not encrypted");
            }
        }

        function decrypt($data)
        {
            try
            {

                $base64Decoded = @base64_decode($data);
                $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->cipher, $base64Decoded, MCRYPT_MODE_CBC, $this->iv);
                return $this->strippadding($decrypted);
            }
            catch (Exception $e)
            {
                throw new Exception("Failed to decrypt");
            }
        }
    }
?>
