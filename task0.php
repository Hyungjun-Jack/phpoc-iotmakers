<?php

include_once "grove_temperature.php";
include_once "sn_iotmakers.php";
include_once "system.php";

define("DATA_SEND_INTERVAL", 3000);
define("ENVU_LENGTH", 1520);

//---------------------------------------------------------
// check CRC.
// 
if(system_crc_check() == FALSE)
{
	system_initialize();
}
//---------------------------------------------------------

$device_id = find_device_id();
$device_pwd = find_device_password();
$gateway_id = find_gateway_id();
im_init(0, $device_id, $device_pwd, $gateway_id);

$sent_tick = im_get_tick();

while(1)
{
    $current_tick = im_get_tick();
    if($current_tick - $sent_tick >= DATA_SEND_INTERVAL)
    {
        $sent_tick = $current_tick;

        //-----------------------------------------------------
        $temperature = read_temperature(0, 0);
        error_log("temperature> $temperature");
        im_send_numdata("temperature", $temperature);
        //-----------------------------------------------------
    }

    im_loop();
}
