<?php

    $response = exec("ssh  bits@admin.boardiesitsolutions.com -p24 -L 3307:localhost:3306 -N &", $output);
    
    echo "Response: $response<br /><br />";

    echo "<pre>";
    print_r($output);
    echo "</pre><br /><br/ >";

?>