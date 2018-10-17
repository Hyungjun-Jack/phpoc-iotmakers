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

function get_item($src, $index, $delimiter)
{
	$len = strlen($src);
	$item = "";
	$item_count = 0;
	
	$pos1 = 0;
	$pos2 = 0;

	while(1)
	{
		$pos2 = strpos($src, $delimiter, $pos1);
		
		if($pos2 === FALSE)
		{
			return "";
		}
		
		if($item_count == $index)
		{
			$value = substr($src, $pos1, $pos2 - $pos1);
			return $value;
		}
		else
		{
			$pos1 = $pos2 + 1;
			$item_count++;
		}
	}
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

  $result = "";
  $item_count = 0;
  $delimiter = ";";		
  $total_len = 0;
  $overflow = false;
  $j = 1;	

  for($ch = 1; $ch <= 13; $ch++)
  {
    // check buffer status
    if($overflow == true)
    {
      break;
    }
    
    $n = pid_ioctl($pid, "scan result $ch");
    
    for($id = 0; $id < $n; $id++)
    {
      $value = pid_ioctl($pid, "scan result $ch $id");
      
      // check buffer size (overflow check) 
      if(MAX_STRING_LEN < $total_len + strlen($value))
      {
        $overflow = true;
        break;
      }
      
      // update total length
      $total_len = $total_len + strlen($value);
      
      if($value)
      {
        $add = false;
        $compare = "";
        $buf = "";
        $result_len = strlen($result);
    
        // compare
        $pos1 = 0;
        $pos2 = 0;
        
        while(1)
        {
          $pos2 = strpos($result, $delimiter, $pos1);
          
          if($pos2 === FALSE)
          {
            break;
          }
      
          $compare = substr($result, $pos1, $pos2 - $pos1);
        
          $scan = explode(" ", $value);
          $value1  = (int)$scan[1];
    
          $scan = explode(" ", $compare);
          $value2  = (int)$scan[1];
    
          if($value1 < $value2)
          {
            $add = true;
            $temp = $buf. $value. $delimiter. $compare. substr($result, $pos2);
            $result = $temp;
            break;
          }
          $buf = $buf. $compare. $delimiter;
          $compare = "";

          $pos1 = $pos2 + 1;
        }
        
        // add tail
        if($add == false) 
        {
          $result = $result. $value. $delimiter;
        }
        $value = "";
        $item_count++;
      }
      else
      {
        break;
      }
    }
  }

  if($item_count > 0)
  {
    echo ",\"ap\":[";
  }

  $valid_item_count = 0;
  for($i = 0; $i < $item_count; $i++)
  {
    $value = get_item($result, $i, $delimiter);
    
    if($value)
    {
      $scan = explode(" ", $value);
      
      $ch    = (int)$scan[0];
      $rssi  = (int)$scan[1];
      $flags = bin2int(hex2bin($scan[2]), 0, 1);
      $ssid_raw  = $scan[3];
      $ssid  = hex2bin($scan[3]);
      
      if($flags & 0x20)
        $security = "IBSS";
      else
        $security = "BSS";

      if($flags & 0x04)
        $security = "WPA2";
      else
      if($flags & 0x02)
        $security = "WPA";
      else
      if($flags & 0x01)
        $security = "WEP";
      else
        $security = "None";

      if(!is_printable($ssid))
        continue;		
      else 
        $j++;
      
      if($valid_item_count != 0)
        echo ",";
      
      echo "{\"ssid\":\"$ssid\",\"security\":\"$security\",\"rssi\":\"$rssi\",\"ssid_raw\":\"$ssid_raw\"}";
      
      $valid_item_count++;
    }
    else
      break;
  }

  if($item_count > 0)
  {
    echo "]";
  }
  echo ",\"item_count\":$valid_item_count";
  echo "}";	
}
pid_close($pid);

?>