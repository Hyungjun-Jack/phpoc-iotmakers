<?php 
include_once "/lib/sd_340.php";

$example_led_pin = 14;
uio_setup(0, $example_led_pin, "out");

function im_tag_handler($tag_name, $tag_value)
{
    global $example_led_pin;

    error_log("im_tag_handler: $tag_name, $tag_value");

    if(is_string($tag_value))
    {
        error_log("im_tag_handler: string.");
    }
    else
    {
        error_log("im_tag_handler: float.");
    }

    switch($tag_name)
    {
        case "led":
            if(is_string($tag_value) && $tag_value == "on")
                uio_out(0, $example_led_pin, HIGH);
            else if(is_string($tag_value) && $tag_value == "off")
                uio_out(0, $example_led_pin, LOW);
        break;
    }
}
?>