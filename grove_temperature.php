<?php

/*
refer to link below:
https://github.com/Seeed-Studio/Sketchbook_Starter_Kit_V2.0/blob/master/Grove_Temperature_Sensor/Grove_Temperature_Sensor.ino
*/

function read_temperature($adc, $channel)
{
    $adc_value = 0;
    $pid = pid_open("/mmap/adc$adc");
    pid_ioctl($pid, "set ch $channel");
    pid_read($pid, $adc_value);
    
    $resistance = (float)(4095 - $adc_value) * 10000 / $adc_value; // get resistance
    $temperature = 1 / (log($resistance / 10000) / 3975 + 1 / 298.15) - 273.15; // calc temperature
    $temperature = (float)round($temperature * 10) / 10;
    
    pid_close($pid);

    return $temperature;
}

?>