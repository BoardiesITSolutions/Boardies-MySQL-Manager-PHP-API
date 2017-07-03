<?php

    require_once 'DBHelper.php';
    require_once 'XMLGenerator.php';
    require_once 'CommonTasks.php';
    require_once 'ConfigManager.php';

    class TunnelManager
    {
        private $sshHost;
        private $sshUsername;
        private $sshPassword;
        private $sshPort;
        private $authMethod;
        private $privateSSHKey;
        private $certificatePassphrase;
        private $mysqlHost;
        private $mysqlPort;
        private $configManager;
        private $encryption;
        private $fingerprintConfirmed;
        private $fingerprint;
        
        public function __construct() 
        {
            $this->configManager = new ConfigManager("mysql.conf");
            $this->encryption = new Encryption();
        }

        public function getFingerprintConfirmed()
        {
            return $this->fingerprintConfirmed;
        }
        
        public function setSSHParametersFromPostArray($postArray)
        {
            $this->sshHost = $postArray["sshHost"];
            $this->sshUsername = $postArray["sshUsername"];
            $this->authMethod = $postArray["authMethod"];
            $this->fingerprintConfirmed = $postArray["fingerprintConfirmed"];

            if ($this->authMethod === "Password" || empty($this->authMethod))
            {
                $this->sshPassword = $postArray["sshPassword"];

                //If the auth method hasn't been set due to being an older app, then set it
                if (empty($this->authMethod))
                {
                    $this->authMethod = "Password";
                }
            }
            else if ($this->authMethod === "PrivateKey")
            {
                $this->privateSSHKey = $postArray["sshPrivateKey"];
                if (isset($postArray["certPassphrase"]) && !empty($postArray["certPassphrase"]))
                {
                    $this->certificatePassphrase = $postArray["certPassphrase"];
                }
            }
            if (isset($postArray["fingerprint"]))
            {
                $this->fingerprint = $postArray["fingerprint"];
            }
            $this->sshPort = $postArray["sshPort"];
            $this->mysqlPort = $postArray["port"];
            $this->mysqlHost = $postArray["server"];
        }
        
        public function closeTunnel($port)
        {
            try
            {
                $socket = $this->returnSocket();
                
                /*$xmlGenerator = new XMLGenerator();
                $xmlGenerator->writeStartDocument();
                $xmlGenerator->writeStartElement("CloseTunnel");
                $xmlGenerator->writeElementString("LocalPort", $port);
                $xmlGenerator->writeEndElement();
                
                $input = $xmlGenerator->returnXmlString();*/

                $request = new StdClass();
                $request->method = "CloseTunnel";
                $request->localPort = intval($port);

                $input = json_encode($request);
    
                socket_write($socket, $input, strlen($input));
                socket_close();
            }
            catch (Exception $e)
            {
                $helperClass = new HelperClass();
                $response = $helperClass->returnError("SSHTunnelNotAvailable");
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded(null, $response);
                exit();
            }
        }
        
        public function startTunnel()
        {
            require_once 'Logger.php';
            $logger = new Logger();
            try {
                $socket = $this->returnSocket();
                $request = new stdClass();
                $request->method = "CreateTunnel";

                $request->sshDetails = new stdClass();
                $request->sshDetails->sshUsername = $this->sshUsername;
                $request->sshDetails->authMethod = $this->authMethod;
                if ($this->authMethod === "Password") {
                    $request->sshDetails->sshPassword = $this->sshPassword;
                } else {
                    $request->sshDetails->privateSSHKey = $this->privateSSHKey;
                    $request->sshDetails->certPassphrase = $this->certificatePassphrase;
                }
                $request->sshDetails->sshPort = intval($this->sshPort);
                $request->sshDetails->sshHost = $this->sshHost;
                $request->remoteMySQLPort = intval($this->mysqlPort);
                $request->mysqlHost = $this->mysqlHost;
                $request->fingerprintConfirmed = ($this->fingerprintConfirmed === "true") ? true : false;
                if (!empty($this->fingerprint))
                {
                    $request->fingerprint = $this->fingerprint;
                }



                $socketData = json_encode($request);
                socket_write($socket, $socketData, strlen($socketData));

                if (isset($request->sshDetails->sshPassword))
                {
                    for ($i = 0; $i < strlen($request->sshDetails->sshPassword); $i++)
                    {
                        $request->sshDetails->sshPassword[$i] = '*';
                    }
                }
                else if (isset($request->sshDetails->privateSSHKey))
                {
                    for ($i = 0; $i < strlen($request->sshDetails->privateSSHKey); $i++)
                    {
                        $request->sshDetails->privateSSHKey[$i] = '*';
                    }

                    if (isset($request->sshDetails->certPassphrase) && !empty($request->sshDetails->certPassphrase))
                    {
                        for ($i = 0; $i < strlen($request->sshDetails->certPassphrase); $i++)
                        {
                            $request->sshDetails->certPassphrase[$i] = '*';
                        }
                    }
                }

                $logger->writeToLog("Sending Request: " . json_encode($request));

                $jsonResponse = json_decode(socket_read($socket, 1024));
                socket_close($socket);
                return $jsonResponse;
            }
            catch (Exception $ex)
            {
                $helperClass = new HelperClass();
                $response = $helperClass->returnError("SSHTunnelNotAvailable");
                HelperClass::printResponseInCorrectEncodingAndCloseTunnelIfNeeded(null, $response);
                exit();
            }
            /*try
            {
                $socket = $this->returnSocket();
                
                /*$xmlGenerator = new XMLGenerator();
                $xmlGenerator->writeStartDocument();
                $xmlGenerator->writeStartElement("CreateTunnel");
                $xmlGenerator->writeElementString("SSHUsername", $this->sshUsername);
                $xmlGenerator->writeElementString("AuthMethod", $this->authMethod);
                if ($this->authMethod === "Password")
                {
                    $xmlGenerator->writeElementString("SSHPassword", $this->sshPassword);
                }
                else
                {
                    $xmlGenerator->writeElementString("PrivateSSHKey", $this->privateSSHKey);
                    if (isset($this->certificatePassphrase) && !empty($this->certificatePassphrase))
                    {
                        $xmlGenerator->writeElementString("CertPassphrase", $this-> certificatePassphrase);
                    }
                }
                $xmlGenerator->writeElementString("SSHPort", $this->sshPort);
                $xmlGenerator->writeElementString("SSHHost", $this->sshHost);
                $xmlGenerator->writeElementString("RemoteMySQLPort", $this->mysqlPort);
                $xmlGenerator->writeElementString("MySQLHost", $this->mysqlHost);
                $xmlGenerator->writeEndElement();
                
                $socketData = $xmlGenerator->returnXmlString();
                socket_write($socket, $socketData, strlen($socketData));
                
                $xmlResponse = socket_read($socket, 1024);
                socket_close($socket);
                
                $responseArr = new SimpleXMLElement($xmlResponse);
                if ($responseArr->Message->__toString() !== null && $responseArr->Status->__toString() !== "Success")
                {
                    throw new Exception($responseArr->Message->__toString());
                }
                else
                {
                    $helperClass = new HelperClass();
                    $response = $helperClass->returnSuccessArray(null);
                    $response[LOCAL_TUNNEL_PORT] = $responseArr->LocalTunnelPort->__toString();
                    return $response;
                }
            } 
            catch (Exception $ex) 
            {
                $helperClass = new HelperClass();
                $response = $helperClass->returnTunnelError($ex->getMessage());
                return $response;
            }*/
        }
        
        private function returnSocket()
        {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket === false)
            {
                throw new Exception("Create Socket Failed: " . socket_strerror(socket_last_error()));
            }
            
            $result = socket_connect($socket, "localhost", $this->configManager->getConfigItemValue("general", "TunnelSocket", 500));
            if ($result === false)
            {
                throw new Exception("Socket Connect Failed: " . socket_strerror(socket_last_error()));
            }
            
            return $socket;
        }
    }
    
?>