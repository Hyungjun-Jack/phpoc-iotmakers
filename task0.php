<?php

include_once "grove_temperature.php";
include_once "sn_iotmakers.php";

define("DATA_SEND_INTERVAL", 3000);

$sent_tick = im_get_tick();

while(1)
{
    $current_tick = im_get_tick();
    if($current_tick - $sent_tick >= DATA_SEND_INTERVAL)
    {
        $sent_tick = $current_tick;
        $temperature = read_temperature(0, 0);
        error_log("$temperature");
    }

    im_loop();
}
