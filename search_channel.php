<?php
set_time_limit(30);

function is_printable($ssid)
{
	$ssid_len = strlen($ssid);

	for($i = 0; $i < $ssid_len; $i++)
	{
		$code = bin2int($ssid, $i, 1);
		if($code == 0x00)
			return false;
	}

	return true;
}

$pid = pid_open("/mmap/net1");

$wlan_status = pid_ioctl($pid, "get mode");

echo "{\"status\":\"$wlan_status\"";
if($wlan_status == "")
{
  echo ",\"item_count\":0}";
}
else
{
  pid_ioctl($pid, "scan qsize 64");

  pid_ioctl($pid, "scan start");
  while(pid_ioctl($pid, "scan state"))
    ;
  
  echo ",\"item_count\":14,\"channels\":[";
  
  for($ch = 1; $ch <= 14; $ch++)
  {
    $n = pid_ioctl($pid, "scan result $ch");
    echo "{\"item_count\":";
    
    $total_ssid = "";
    $item_count = 0;
    for($id = 0; $id < $n; $id++)
    {	
      $scan = pid_ioctl($pid, "scan result $ch $id");

      if($scan)
      {
        $scan = explode(" ", $scan);
        
        $ch    = (int)$scan[0];
        $ssid  = hex2bin($scan[3]);
        
        if(!is_printable($ssid))
          continue;
			$total_ssid .= $ssid . ", " ;
			$item_count++;
      }
      else
        break;
    }
    
    if($total_ssid != "")
    	$total_ssid = substr($total_ssid, 0, strlen($total_ssid) - 2);
    
    echo "$item_count,\"ssid\":\"$total_ssid\",\"channel\":\"$ch\"}";
    
    if($ch < 14)
      echo ",";
  }
  
  echo "]}";
}

pid_close($pid);
?>