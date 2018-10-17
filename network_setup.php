<?php
$referer = explode("/", _SERVER("HTTP_REFERER"));

if(!_SERVER("HTTP_REFERER") || ($referer[3] != "" && $referer[3] != "index.php"))
{
	exit "<h4>ERROR : You were refered to this page from a unauthorised source.</h4></body>\r\n</html>\r\n";
}

set_time_limit(30);

include_once "/lib/sc_envs.php";
include_once "system.php";

define("IP6_DHCP_DNS", 0x00000a0a);

$envs = envs_read();

//---------------------------------------------------------------------------
// IP4
//
$_4_type = (int)_POST("4_type"); // 1: DHCP, 0: STATIC
envs_set_net_opt($envs, NET_OPT_DHCP, $_4_type);

if($_4_type == 1)
{
  $_4_dhcp_dns = (int)_POST("4_dhcp_dns"); // 1: 수동입력, 0: 자동
  
  if($_4_dhcp_dns == 1)
  {
    envs_set_net_opt($envs, NET_OPT_AUTO_NS, 0);
    $_4_dns = _POST("4_dns");
    envs_update($envs, ENV_CODE_IP4, 0x03, inet_pton($_4_dns));
  }
  else
  {
    envs_set_net_opt($envs, NET_OPT_AUTO_NS, 1);
  }
}
else if($_4_type == 0)
{
  $_4_addr = inet_pton(_POST("4_addr"));
  $_4_subnet = inet_pton(_POST("4_subnet"));
  $_4_gateway = inet_pton(_POST("4_gateway"));
  $_4_dns = inet_pton(_POST("4_dns"));

  $_4_gateway = $_4_gateway === FALSE ? "" : $_4_gateway;
  $_4_dns = $_4_dns === FALSE ? "" : $_4_dns;
  
  envs_update($envs, ENV_CODE_IP4, 0x00, $_4_addr);
	envs_update($envs, ENV_CODE_IP4, 0x01, $_4_subnet);
	envs_update($envs, ENV_CODE_IP4, 0x02, $_4_gateway);
	envs_update($envs, ENV_CODE_IP4, 0x03, $_4_dns);
}
//---------------------------------------------------------------------------

//---------------------------------------------------------------------------
// IP6
//
if(ini_get("init_ip6") === "1")
{
  $_6_enable = (int)_POST("6_enable"); //1: Enable, 0: Disable
  envs_set_net_opt($envs, NET_OPT_IP6, $_6_enable);
  
  if($_6_enable == 1)
  {
    $_6_type = (int)_POST("6_type"); // 1: STATIC, 0: DHCP
    $_6_eui = (int)_POST("6_eui");
    
    envs_set_net_opt($envs, NET_OPT_IP6_GUA, $_6_type);
    envs_set_net_opt($envs, NET_OPT_IP6_EUI, $_6_eui);
    
    if($_6_type == 0)
    {
      $_6_dhcp_dns = (int)_POST("6_dhcp_dns"); // 1: 수동입력, 0: 자동
      
      if($_6_dhcp_dns == 1)
      {
        envs_set_net_opt($envs, IP6_DHCP_DNS, 0);
        
        $_6_dns = _POST("6_dns");
        envs_update($envs, ENV_CODE_IP6, 0x03, inet_pton($_6_dns));
      }
      else
      {
        envs_set_net_opt($envs, IP6_DHCP_DNS, 1);
      }
    }
    else if($_6_type == 1)
    {
      $_6_addr = _POST("6_addr");
      $_6_prefix = (int)_POST("6_prefix");
      $_6_gateway = inet_pton(_POST("6_gateway"));
      $_6_dns = inet_pton(_POST("6_dns"));
  
      $_6_gateway = $_6_gateway === FALSE ? "" : $_6_gateway;
      $_6_dns = $_6_dns === FALSE ? "" : $_6_dns;
  
      envs_update($envs, ENV_CODE_IP6, 0x00, inet_pton($_6_addr) . int2bin($_6_prefix, 2));
      envs_update($envs, ENV_CODE_IP6, 0x02, $_6_gateway);
      envs_update($envs, ENV_CODE_IP6, 0x03, $_6_dns);
    }
  }
}
//---------------------------------------------------------------------------

//---------------------------------------------------------------------------
// WLAN
//
if(ini_get("init_net1") === "1")
{
  $w_enable = (int)_POST("w_enable"); //1: Enable, 0: Disable
  envs_set_net_opt($envs, NET_OPT_WLAN, $w_enable);
  
  if($w_enable == 1)
  {
    $w_type = (int)_POST("w_type"); //0: Ad-Hoc, 1: Infrastructure, 2: SoftAP
    envs_set_net_opt($envs, NET_OPT_TSF, $w_type);
    
    if($w_type == 0 || $w_type == 2)
    {
      $wlan_channel = (int)_POST("channel");
      envs_set_net_opt($envs, NET_OPT_CH, $wlan_channel);
    }
    
    $wlan_ssid = bin2hex(_POST("ssid"));
    $wlan_ssid_raw = _POST("ssid_raw");
    $wlan_shared_key = _POST("shared_key");

    if($wlan_ssid != $wlan_ssid_raw)
      $wlan_ssid = hex2bin($wlan_ssid);
    else
      $wlan_ssid = hex2bin($wlan_ssid_raw);
    
    if($wlan_ssid != rtrim(envs_find($envs, ENV_CODE_WLAN, 0x01)))
      $comp_psk = true;
    else if($wlan_shared_key != rtrim(envs_find($envs, ENV_CODE_WLAN, 0x08)))
      $comp_psk = true;
    else
      $comp_psk = false;

    if($comp_psk)
    {
      // psk generation take 0.5 second on STM32F407 168MHz
      $wpa_psk = hash_pbkdf2("sha1", $wlan_shared_key, $wlan_ssid, 4096, 32, true);
      envs_update($envs, ENV_CODE_WLAN, 0x09, $wpa_psk);
    }

    envs_update($envs, ENV_CODE_WLAN, 0x01, $wlan_ssid);	
    envs_update($envs, ENV_CODE_WLAN, 0x08, $wlan_shared_key);
    
    $wlan_phy_mode = (int)_POST("phy_mode");
    $wlan_preamble = (int)_POST("preamble");
    $wlan_slot = (int)_POST("slot");
    $wlan_cts = (int)_POST("cts");
    
    envs_set_net_opt($envs, NET_OPT_PHY, $wlan_phy_mode);
    if($wlan_phy_mode == 2)
    {
      envs_set_net_opt($envs, NET_OPT_SHORT_PRE, $wlan_preamble);
    }
    else if($wlan_phy_mode == 3)
    {
      envs_set_net_opt($envs, NET_OPT_SHORT_PRE, $wlan_preamble);
      envs_set_net_opt($envs, NET_OPT_SHORT_SLOT, $wlan_slot);
      envs_set_net_opt($envs, NET_OPT_CTS_PROT, $wlan_cts);
    }
  }
}
//---------------------------------------------------------------------------

$wkey = envs_get_wkey(); 
envs_write($envs, $wkey);
?>

<script>
parent.network_setup_finish();
</script>