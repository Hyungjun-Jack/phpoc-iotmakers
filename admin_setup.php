<?php
$referer = explode("/", _SERVER("HTTP_REFERER"));

if(!_SERVER("HTTP_REFERER") || ($referer[3] != "" && $referer[3] != "index.php"))
{
	exit "<h4>ERROR : You were refered to this page from a unauthorised source.</h4></body>\r\n</html>\r\n";
}

set_time_limit(30);

include_once "system.php";

//---------------------------------------------------------------------------
// ADMIN PWD
//
$web_password = _POST("admin_pwd");

$setting = find_setting(CODE_ADMIN, 0x00);

$copy_len = strlen($web_password) > 8 ? 8 : strlen($web_password);
if($copy_len < 8)
  $web_password .= str_repeat("\x00", 8 - $copy_len);
$setting = substr_replace($setting, $web_password, 0, 8);

update_setting(CODE_ADMIN, 0, $setting);

// POLLING
$setting = find_setting(CODE_ADMIN, 0x01);
update_setting(CODE_ADMIN, 1, int2bin((int)_POST("polling_enable"), 1));

$interval = _POST("polling_interval");
if($interval != "")
{
  $setting = find_setting(CODE_ADMIN, 0x02);
  update_setting(CODE_ADMIN, 2, int2bin((int)_POST("polling_interval"), 4));
}
//---------------------------------------------------------------------------

?>

<script>
parent.admin_setup_finish();
</script>