<?php


    
    class CommonTasks
    {
        /**
         * Returns the visiting users IP Address
         * @return String
         */
        function getIP()
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
        
        /**
         * Will generate a random password at the specified length. If length
         * omitted then it defaults to false
         * @param int $length
         * @return string
         */
        function generateRandomString($length = 6, $possible = null)
        {
            $string = "";
            if (empty($possible))
            {
                $possible = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!£$&*";
            }

            $maxLength = strlen($possible);
            if ($length > $maxLength)
            {
                $length = $maxLength;
            }

            $i = 0;
            while ($i < $length)
            {
                $char = substr($possible, mt_rand(0, $maxLength-1), 1);
                if (!strstr($string, $char))
                {
                    $string .= $char;
                    $i++;
                }
            }
            return $string;
        }
        
        /**
         * @deprecated since version 1.1
         * Will generate a random password at the specified length. If length
         * omitted then it defaults to false
         * @param int $length
         * @return string
         */
        
        function generatePassword($length = 6)
        {
            $password = "";
            $possible = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!£$&*";

            $maxLength = strlen($possible);
            if ($length > $maxLength)
            {
                $length = $maxLength;
            }

            $i = 0;
            while ($i < $length)
            {
                $char = substr($possible, mt_rand(0, $maxLength-1), 1);
                if (!strstr($password, $char))
                {
                    $password .= $char;
                    $i++;
                }
            }
            return $password;
        }
        
        function generatePasswordOnlyNumbers($length = 6)
        {
            $password = "";
            $possible = "1234567890";

            $maxLength = strlen($possible);
            if ($length > $maxLength)
            {
                $length = $maxLength;
            }

            $i = 0;
            while ($i < $length)
            {
                $char = substr($possible, mt_rand(0, $maxLength-1), 1);
                if (!strstr($password, $char))
                {
                    $password .= $char;
                    $i++;
                }
            }
            return $password;
        }

        
        /**
         * Will limit the length of the message that is passed. I.e. to make
         * long strings which are being shown in smaller spaces to avoid
         * ugly wrapping issues
         * @param string $message
         * @param int $length
         * @return string
         */
        function limitString($message, $length)
        {
            $arr = explode(' ',$message);
            $string = '';

            foreach($arr as $word) {
                if(strlen($string) > $length) break;
                $string .= ' ' . $word;
            }

            return $string;
        }
        
        /**
         * Returns a date and time string in the format that MySQL Database wants it
         * @return string
         */
        function getDBDateTime()
        {
            $date = date('Y-m-d');
            $t = time();
            $time = date('H:i:s', $t);

            return "$date $time";
        }
        
        /**
         * Returns a long value of an epoch time stamp, this is based on the
         * current time
         * @return long
         */
        function getEpochTimeStamp()
        {
            $date = new DateTime();
            return date_timestamp_get($date);
        }
        
        /**
         * Returns the time in the format of H:i
         * @return string
         */
        function getTime()
        {
            date_default_timezone_set('Europe/London');
            $time = date("H:i");
            return $time;
        }
        
        /**
         * Returns a date string in a specific format. The format is an optional
         * parameter so if it is null it will default with YYYY/mm/dd otherwise
         * it will return the date in the specific format
         * @param string $format
         * @return string
         */
        function findDateInSpecificFormat($format=null)
        {
            date_default_timezone_set('Europe/London');
            $date = getdate();
            $day = $date['mday'];
            $month = $date['mon'];
            $year = $date['year'];
            if ($format == null)
            {
                return $year . '/' . $month . '/' . $day;
            }
            else
            {
                $returnedDate = str_replace('Y', $year, $format);
                $returnedDate = str_replace('m', $month, $returnedDate);
                $returnedDate = str_replace('d', $day, $returnedDate);

                return $returnedDate;
            }
        }
    }
?>
