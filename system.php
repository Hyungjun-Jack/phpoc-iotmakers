<?php

include_once "/lib/sd_spc.php";
include_once "/lib/sd_340.php";

define("PROJECT_ID", "PROJECT_2018_4_04");
define("DEFAULT_ADMIN_PWD", "admin\x00\x00\x00");

define("NM0_LENGTH", 2048);
define("CODE_ADMIN", 0x01);

function system_crc_check()
{
  error_log("checking crc of settings......");
  
  $nm0_pid = pid_open("/mmap/nm0");
  $crc = 0;
  $result = TRUE;
  
  pid_read($nm0_pid, $crc, 2);
  
  if($crc == 0)
  {
    error_log("nm0 is not initialized.");
    $result = FALSE;  
  }
  else
  {
    $crc16 = (int)system("crc 16 %1 0000 a001 lsb", PROJECT_ID);
    if($crc != $crc16)
    {
      error_log("crc is not match.");
      $result = FALSE;
    }
    else
    {
      error_log("OK.");
    }
  }
  pid_close($nm0_pid);
  return $result;
}

function make_setting_block($code, $id, $setting_data)
{
  $pad_size = (strlen($setting_data) + 2) % 4;
  if($pad_size != 0)
    $pad_size = 4 - $pad_size;
  $block_length = 4 + strlen($setting_data) + $pad_size + 2;
  $setting_block = int2bin($code, 1) . int2bin($id, 1) . int2bin($block_length, 2) . $setting_data;
  
  if($pad_size > 0)
    $setting_block .= str_repeat("\x00", $pad_size);
    
  $crc = (int)system("crc 16 %1", $setting_block);
  $setting_block .= int2bin($crc, 2);
  
  return $setting_block;
}

function system_initialize()
{
  $settings = "";
  
  $nm0_pid = pid_open("/mmap/nm0");
  
  $crc = (int)system("crc 16 %1 0000 a001 lsb", PROJECT_ID);
  pid_write($nm0_pid, int2bin($crc, 2));
  
  //----------------------------------------------------------------------
  // ADMIN...
  $settings = make_setting_block(CODE_ADMIN, 0x00, DEFAULT_ADMIN_PWD);
  pid_write($nm0_pid, $settings);
  //----------------------------------------------------------------------
  
  // ENV_CODE_EOE
  $setting_block = "";
  $settings = make_setting_block(0xff, 0xff, $setting_block);
  pid_write($nm0_pid, $settings);
  
  $pad_length = NM0_LENGTH - strlen($settings) - 2;
  while($pad_length > 0)
  {
    pid_write($nm0_pid, "\x00");
    $pad_length--;
  }
  pid_close($nm0_pid);
  error_log("settings has been initialized\r\n");
}

function settings_dump()
{
  error_log("/mmap/nm0\r\n");

  $env = "";
  $code = 0;
  $id = 0;

  $nm0_pid = pid_open("/mmap/nm0");

  pid_lseek($nm0_pid, 2, SEEK_CUR);
  
  $offset = 0;

  $settings = "";
  $read = pid_read($nm0_pid, $settings, NM0_LENGTH - 2);
  
  while($offset < $read)
  {
    $code = bin2int($settings, $offset, 1);
    $id = bin2int($settings, $offset + 1, 1);
    $len = bin2int($settings, $offset + 2, 2);
    $setting_block = substr($settings, $offset, $len);
    
    $sub_block = substr($setting_block, 0, $len - 2);
    $crc2 = (int)system("crc 16 %1", $sub_block);
    
    $crc = substr($setting_block, $len - 2, 2);
    if($crc2 != bin2int($crc, 0, 2))
    {
      error_log("crc fail.....\r\n");
      hexdump($setting_block);
    }
  
    error_log(sprintf("code - %02x, id - %02x, len - $len\r\n", $code, $id));
    
    $offset += $len;
    
    if($code == 0xff)
      break;
  }

  pid_close($nm0_pid);

  error_log( "\r\n");
}

function find_setting($req_code, $req_id)
{
  $nm0_pid = pid_open("/mmap/nm0");
  pid_lseek($nm0_pid, 2, SEEK_CUR);
  
  $offset = 2;
  $rbuf = "";
  
  while($offset < NM0_LENGTH)
  {
    pid_read($nm0_pid, $rbuf, 1); $code = bin2int($rbuf, 0, 1);
    pid_read($nm0_pid, $rbuf, 1); $id = bin2int($rbuf, 0, 1);
    pid_read($nm0_pid, $rbuf, 2); $len = bin2int($rbuf, 0, 2);

    if(($code == $req_code) && ($id == $req_id))
    {
      $block = "";
      pid_lseek($nm0_pid, $offset, SEEK_SET);
      pid_read($nm0_pid, $block, $len - 2); // block
      pid_read($nm0_pid, $rbuf, 2); $crc = bin2int($rbuf, 0, 2); // CRC

      if($crc != (int)system("crc 16 %1", $block))
        die("setting crc error\r\n");
      
      pid_close($nm0_pid);
      return substr($block, 4, $len - 4 - 2);
    }
    else
    {
      pid_lseek($nm0_pid, $len - 4, SEEK_CUR);
    }

    if($code == 0xff)
      break;

    $offset += $len;
  }
  
  pid_close($nm0_pid);
  return "";
}

function update_setting($req_code, $req_id, $setting_data)
{
  $nm0_pid = pid_open("/mmap/nm0");
  pid_lseek($nm0_pid, 2, SEEK_CUR);
  
  $offset = 2;
  $nm0_head = "";
  
  while($offset < NM0_LENGTH)
  {
    pid_read($nm0_pid, $nm0_head, 4);
    $code = bin2int($nm0_head, 0, 1);
    $id = bin2int($nm0_head, 1, 1);
    $len = bin2int($nm0_head, 2, 2);
    
    if(($code == $req_code) && ($id == $req_id))
    {
      if(strlen($setting_data) > ($len - 4 - 2))
        exit("nm0_update: The length of setting_data is not match of NM0.\r\n");
      
      $pad_size = $len - (4 + strlen($setting_data) + 2);
      
      if($pad_size > 0)
				$setting_data .= str_repeat("\x00", $pad_size);
      
      $setting_data = $nm0_head . $setting_data;
      $nm0_crc = (int)system("crc 16 %1", $setting_data);
      $setting_data .= int2bin($nm0_crc, 2);
      
      pid_lseek($nm0_pid, $offset, SEEK_SET);
      pid_write($nm0_pid, $setting_data);
      pid_close($nm0_pid);
      return $len;
    }
    else
    {
      pid_lseek($nm0_pid, $len - 4, SEEK_CUR);
    }
    
    if($code == 0xff)
      break;
    
    $offset += $len;
  }
  
  pid_close($nm0_pid);
  
  return 0;
}

function find_admin_password()
{
  $pwd = "";
  
  $setting = find_setting(CODE_ADMIN, 0);
  if($setting != "")
  {
    $pwd = rtrim($setting, "\x00");
  }
  
  return $pwd;
}

function send_401()
{
  header('HTTP/1.1 401 Unauthorized');
  header('WWW-Authenticate: Basic realm="PHPoC Authorization"');
  header('Cache-Control: no-cache, no-store, max-age=1, must-revalidate');

  echo "<html>\r\n" ,
    "<head><title>PHPoC Authorization</title>\r\n" ,
    "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\r\n" ,
    "<style>* {box-sizing: border-box}body {font-family: \"Lato\", sans-serif;}</style></head>" ,
    "<body>\r\n" ,
    "<h3>비밀번호를 입력하세요.</h3>\r\n" ,
    "<h4>기본 사용자 이름은 admin입니다.</h4>\r\n" ,
    "</body></html>\r\n";
}

?>