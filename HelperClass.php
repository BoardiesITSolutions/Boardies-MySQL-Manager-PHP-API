<?php

error_reporting(1);
    class HelperClass 
    {
        function returnMySQLErrorArray($errorMsg, $errorCode)
        {
            $status = array();
            $status[RESULT] = ERROR;
            $status[MYSQL_ERROR] = $errorMsg;
            $status[ERROR_NO] = $errorCode;
            return $status;
        }

        function returnError($errorMessage)
        {
            $status = array();
            $status[RESULT] = ERROR;
            $status[MESSAGE] = $errorMessage;
            return $status;
        }
        
        function returnSuccessArray($data)
        {
            $array = array();
            $array[RESULT] = SUCCESS;
            if ($data !== null)
            {
                $array[DATA] = $data;
            }
            return $array;
        }
        
        function returnTunnelError($errorMessage)
        {
            $array = array();
            $array[RESULT] = ERROR;
            $array[MESSAGE] = $errorMessage;
            return $array;  
        }

        /**
         * Prints out the output to be picked up by Android in the correct format.
         * If using version 1.2.0.0 or above then the output is encrypted before it is sent
         * back, older versions (older versions don't post the version) revert back to
         * the previous behaviour and the output is not encrypted
         * @param type $postArray The array that was posted from android (includes the version)
         * @param type $response The response that should be json_encoded and possibly encrypted depending on version
         * @throws Exception
         */
        public static function printResponseInCorrectEncodingAndCloseTunnelIfNeeded($postArray, $response)
        {
            require_once 'Encryption.php';
            require_once 'TunnelManager.php';
            $encryption = new Encryption();
            //If the version is set and is version 1.2.0.0 or higher than 
            //print out an encrypted response
            
            //Remove any tunnelling information if tunnel is not enabled
            if ((isset($response->LocalTunnelPort) &&
                empty($response->LocalTunnelPort) || $response->LocalTunnelPort === "-1") ||
                $response->LocalTunnelPort === null)
            {
                unset($response->LocalTunnelPort);
            }
            
            if (isset($postArray["version"]) && $postArray["version"] >= "1.2.0.0")
            {
                
                print $encryption->encrypt(json_encode($response));
            }
            else
            {
                if (isset($response->data))
                {
                    print json_encode($response->data);
                }
                else
                {
                    print json_encode($response);
                }
            }
            if (isset($response->LocalTunnelPort) && !empty($response->LocalTunnelPort) && $response->LocalTunnelPort !== "-1")
            {
                $tunnelManager = new TunnelManager();
                $tunnelManager->closeTunnel($response->LocalTunnelPort);
            }
            /*else if (isset($response[TUNNEL_STATUS]) && $response[TUNNEL_STATUS][LOCAL_TUNNEL_PORT] && $response[TUNNEL_STATUS][LOCAL_TUNNEL_PORT] !== "-1")
            {
                $tunnelManager = new TunnelManager();
                $tunnelManager->closeTunnel($response[TUNNEL_STATUS][LOCAL_TUNNEL_PORT]);
            }*/
        }

        /**
         * Return the clients IP address
         * @return string
         */
        public static function getIP()
        {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
            {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            else
            {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            return $ip;
        }
    }
