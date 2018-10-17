<?php
$referer = explode("/", _SERVER("HTTP_REFERER"));

if(!_SERVER("HTTP_REFERER") || ($referer[3] != "" && $referer[3] != "index.php"))
{
	exit "<h4>ERROR : You were refered to this page from a unauthorised source.</h4></body>\r\n</html>\r\n";
}

set_time_limit(30);

include_once "system.php";

//---------------------------------------------------------------------------
// IoTMakers
//
$device_id = _POST("im_device_id");
$device_pwd = _POST("im_device_pwd");
$gateway_id = _POST("im_gateway_id");

$setting = find_setting(CODE_IOTMAKERS, 0);
$copy_len = strlen($device_id) > 20 ? 20 : strlen($device_id);
if($copy_len < 20)
  $device_id .= str_repeat("\x00", 20 - $copy_len);
$setting = substr_replace($setting, $device_id, 0, 20);
update_setting(CODE_IOTMAKERS, 0, $setting);

$setting = find_setting(CODE_IOTMAKERS, 1);
$copy_len = strlen($device_pwd) > 12 ? 12 : strlen($device_pwd);
if($copy_len < 12)
  $device_pwd .= str_repeat("\x00", 12 - $copy_len);
$setting = substr_replace($setting, $device_pwd, 0, 12);
update_setting(CODE_IOTMAKERS, 1, $setting);

$setting = find_setting(CODE_IOTMAKERS, 2);
$copy_len = strlen($gateway_id) > IOTMAKERS_MAX_STRING_LENGTH ? IOTMAKERS_MAX_STRING_LENGTH : strlen($gateway_id);
if($copy_len < IOTMAKERS_MAX_STRING_LENGTH)
  $gateway_id .= str_repeat("\x00", IOTMAKERS_MAX_STRING_LENGTH - $copy_len);
$setting = substr_replace($setting, $gateway_id, 0, IOTMAKERS_MAX_STRING_LENGTH);
update_setting(CODE_IOTMAKERS, 2, $setting);
//---------------------------------------------------------------------------

?>

<script>
parent.iotmakers_setup_finish();
</script>