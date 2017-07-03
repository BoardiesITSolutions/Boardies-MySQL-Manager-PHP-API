<?php

    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "http://localhost/APIManager.php");
    curl_setopt($ch, CURLOPT_POST, 1);
    
    $array = array();
    $array["type"] = "TestDBConnection";
    $array["server"] = "localhost";
    $array["username"] = "root";
    $array["password"] = "54mCuw901";
    $array["port"] = 3306;
    $array["database"] = "CritiMon";
    $array["useTunnel"] = 1;
    $array["sshHost"] = "admin.boardiesitsolutions.com";
    $array["sshUsername"] = "bits";
    $array["sshPassword"] = "54mCuw901";
    $array["sshPort"] = 24;
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($array));
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $serverOutput = curl_exec($ch);
    
    echo $serverOutput;
    
    curl_close($ch);
?>