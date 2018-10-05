<?php
include_once "/lib/sn_tcp_ac.php";
include_once "/lib/sn_json_b1.php";

define("IM_KEEPALIVE_PERIODIC_SEC", 30);
define("IM_READ_TIMEOUT_SEC", 3);
define("IM_SOCKET_TIMEOUT", 3);
define("IM_PACKET_HEAD_LEN", 35);
define("IM_SERVER_IP", "220.90.216.90");
define("IM_SERVER_PORT", 10020);
define("READ_BUFF_LEN", 40);

define("STOP_MARK",	"\b");
define("STRING_VAL", "\"\b\"");

define("ATTR_athnRqtNo", "athnRqtNo");
define("ATTR_athnNo", "athnNo");
define("ATTR_respCd", "respCd");
define("ATTR_respMsg", "respMsg");
define("ATTR_snsnTagCd", "snsnTagCd");
define("ATTR_dataTypeCd", "dataTypeCd");

$FMT_AUTH_DEV_REQ = "{\"extrSysId\":" . STRING_VAL . ",\"devId\":" . STRING_VAL . ",\"athnRqtNo\":" . STRING_VAL . "}";

define("head_01", "\x11\x01\x00\x23"); // protocol version(1), message header type(1), message header length(2) 0x23 = 35
define("head_02_TypeDevAuth", "\x60\xe0"); // Message Type(2bit) + MEP(2bit) + Method Type(12bit)
define("head_02_TypeDevAuth_res", "\xa0\xe0"); 
define("head_02_TypeReport", "\x61\x9b"); // Message Type(2bit) + MEP(2bit) + Method Type(12bit)
define("head_02_TypeReport_res", "\xa1\x9b"); // Message Type(2bit) + MEP(2bit) + Method Type(12bit)
define("head_02_TypeKeepAlive", "\x60\xe7"); // Message Type(2bit) + MEP(2bit) + Method Type(12bit  
define("head_02_TypeKeepAlive_res", "\xa0\xe7"); // Message Type(2bit) + MEP(2bit) + Method Type(12bit)
define("head_02_TypeCtrl_req", "\x62\x0d"); // Message Type(2bit) + MEP(2bit) + Method Type(12bit)
define("head_02_TypeAck", "\xa2\x0d"); // Message Type(2bit) + MEP(2bit) + Method Type(12bit)
define("head_03", "\x00\x00\x01\x4e\x05\x5f\xdf\xce"); // Transactio ID
define("head_04", "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"); // Channel Auth Token (16byte)
define("head_05", "\x00\x00\x03\x00\x00");  // Encryption Usage(1bit) + Encryption Method(7bit), 
                                    // Compression Usage(1bit) + Compression method(7bit), 
                                    // Encoding Type
                                    //Result Code

$sn_tcp_id = 0;
$sn_device_id = "sollaeD1535615200924";
$sn_device_password = "zpd1q95i3";
$sn_gateway_id = "OPEN_TCP_001PTL001_1000003859";
$sn_auth_success = 0;
$sn_athn_no = "";

$sn_tick = 0;
$sn_keepalive_sent_tick = 0;

function im_init($tcp_id, $device_id, $device_password, $gateway_id)
{
    global $sn_device_id, $sn_device_password, $sn_gateway_id;

    $sn_device_id = $device_id;
    $sn_device_password = $device_password;
    $sn_gateway_id = $gateway_id;
}

function im_get_tick()
{
	while(($pid = pid_open("/mmap/st9", O_NODIE)) == -EBUSY)
		usleep(500);

	if(!pid_ioctl($pid, "get state"))
		pid_ioctl($pid, "start");

	$tick = pid_ioctl($pid, "get count");
	pid_close($pid);

	return $tick;
}

function im_tcp_connected()
{
    global $sn_tcp_ac_pid, $sn_tcp_id;

    if($sn_tcp_ac_pid[$sn_tcp_id] == 0 || ($sn_tcp_ac_pid[$sn_tcp_id] != 0 && tcp_state($sn_tcp_id) == TCP_CLOSED))
        return TCP_CLOSED;
    
    return tcp_state($sn_tcp_id);
}

function sn_im_sock_flush()
{
    global $sn_tcp_ac_pid, $sn_tcp_id;

    while(1)
    {
        if(pid_ioctl($sn_tcp_ac_pid[$sn_tcp_id], "get txlen") <= 0)
            break;
    }
}

function sn_body_sizeof_devAuth($gateway_id, $device_id, $device_password)
{
    global $FMT_AUTH_DEV_REQ;
    return strlen($FMT_AUTH_DEV_REQ) - (strlen(STOP_MARK) * 3) + strlen($gateway_id) + strlen($device_id) + strlen($device_password);
}

function sn_im_sock_send($data, $data_len)
{
    global $sn_tcp_id;

    if(im_tcp_connected() != TCP_CONNECTED)
    {
        error_log("sn_im_sock_send: TCP is not connected.");
        return -1;
    }

    return tcp_write($sn_tcp_id, $data, $data_len);
}

function sn_im_sock_recv(&$buf, $rlen)
{
    global $sn_tcp_ac_pid, $sn_tcp_id;
    $prev_tick = im_get_tick();
    $read = 0;
    while(($read = tcp_readn($sn_tcp_id, $buf, $rlen)) != $rlen)
    {
        $current_tick = im_get_tick();
        if($current_tick - $prev_tick >= IM_SOCKET_TIMEOUT * 1000)
        {
            error_log("sn_im_sock_recv: timeout.");
            return -1;
        }
    }

    return $read;
}

function sn_write_length($length)
{
    $buf = int2bin($length, 4, true);
    return sn_im_sock_send($buf, 4);
}

function sn_read_length()
{
    $packet_length = "";
    if(sn_im_sock_recv($packet_length, 4) < 0)
        return -1;

    $packet_length = bin2int($packet_length, 0, 4, true);
    return $packet_length;
}

function sn_head_send_auth_device()
{
    $sent = 0;
    $sent += sn_im_sock_send(head_01, strlen(head_01));
    $sent += sn_im_sock_send(head_02_TypeDevAuth, strlen(head_02_TypeDevAuth));
    $sent += sn_im_sock_send(head_03, strlen(head_03));
    $sent += sn_im_sock_send(head_04, strlen(head_04));
    $sent += sn_im_sock_send(head_05, strlen(head_05));
    return $sent;
}

function sn_head_send_keepalive()
{
    $sent = 0;
    $sent += sn_im_sock_send(head_01, strlen(head_01));
    $sent += sn_im_sock_send(head_02_TypeKeepAlive, strlen(head_02_TypeKeepAlive));
    $sent += sn_im_sock_send(head_03, strlen(head_03));
    $sent += sn_im_sock_send(head_04, strlen(head_04));
    $sent += sn_im_sock_send(head_05, strlen(head_05));
    return $sent;
}

function sn_body_send_devAuth($gateway_id, $device_id, $device_password)
{
    global $FMT_AUTH_DEV_REQ;
    $req = explode(STOP_MARK, $FMT_AUTH_DEV_REQ);
    $sent = 0;
    $sent += sn_im_sock_send($req[0], strlen($req[0]));
    $sent += sn_im_sock_send($gateway_id, strlen($gateway_id));

    $sent += sn_im_sock_send($req[1], strlen($req[1]));
    $sent += sn_im_sock_send($device_id, strlen($device_id));

    $sent += sn_im_sock_send($req[2], strlen($req[2]));
    $sent += sn_im_sock_send($device_password, strlen($device_password));

    $sent += sn_im_sock_send($req[3], strlen($req[3]));

    return $sent;

}

/*
DBG 0123: 0000: 7b 22 61 74 68 6e 52 71 | 74 4e 6f 22 3a 22 31 71   {"athnRqtNo":"1q
DBG 0123: 0000: 31 67 35 66 35 34 73 22 | 2c 22 61 74 68 6e 4e 6f   1g5f54s","athnNo
DBG 0123: 0000: 22 3a 22 30 30 30 30 30 | 30 30 30 33 42 39 41 43   ":"000000003B9AC
DBG 0123: 0000: 46 46 42 30 30 30 30 30 | 30 30 30 33 42 39 43 35   FFB000000003B9C5
DBG 0123: 0000: 45 44 41 22 2c 22 72 65 | 73 70 43 64 22 3a 22 31   EDA","respCd":"1
DBG 0123: 0000: 30 30 22 2c 22 72 65 73 | 70 4d 73 67 22 3a 22 53   00","respMsg":"S
DBG 0123: 0000: 55 43 43 45 53 53 22 7d |                           UCCESS"}
*/
function sn_read_DevAuth_res_body($body_length)
{
    global $sn_auth_success, $sn_athn_no;

    $sn_auth_success = 0;

    $rbuf = "";
    $read = sn_im_sock_recv($rbuf, $body_length);
    if($read < $body_length)
    {
        error_log("sn_read_DevAuth_res_body: fail to reading.");
        return;
    }

    $result = json_search($rbuf, ATTR_athnRqtNo);
    $type = json_text_type($result);
    $value = json_text_value($result);
    error_log("> $type $value\r\n");
    
    $result = json_search($rbuf, ATTR_athnNo);
    $type = json_text_type($result);
    $value = json_text_value($result);
    error_log("> $type $value\r\n");
    $sn_athn_no = $value;
    $sn_auth_success = 1;

    $result = json_search($rbuf, ATTR_respCd);
    $type = json_text_type($result);
    $value = json_text_value($result);
    error_log("> $type $value\r\n");

    $result = json_search($rbuf, ATTR_respMsg);
    $type = json_text_type($result);
    $value = json_text_value($result);
    error_log("> $type $value\r\n");
}

/*
DBG 0130 im_log_hex: 0000: 7b 22 72 65 73 70 43 64 | 22 3a 22 31 30 30 22 2c   {"respCd":"100",
DBG 0130 im_log_hex: 0000: 22 72 65 73 70 4d 73 67 | 22 3a 22 53 55 43 43 45   "respMsg":"SUCCE
DBG 0130 im_log_hex: 0000: 53 53 22 7d                                         SS"}
*/
function sn_read_KeepAlive_res_body($body_length)
{
    $rbuf = "";
    $read = sn_im_sock_recv($rbuf, $body_length);
    if($read < $body_length)
    {
        error_log("sn_read_KeepAlive_res_body: fail to reading.");
        return;
    }

    $result = json_search($rbuf, ATTR_respCd);
    $type = json_text_type($result);
    $value = json_text_value($result);
    error_log("> $type $value\r\n");

    $result = json_search($rbuf, ATTR_respMsg);
    $type = json_text_type($result);
    $value = json_text_value($result);
    error_log("> $type $value\r\n");
}

function sn_head_is_TypeDevAuth_res(&$head)
{  
    $head_type = substr($head, 4, 2);
    return ($head_type == head_02_TypeDevAuth_res ? 1 : 0);
}

function sn_head_is_TypeKeepAlive_res(&$head)
{
    $head_type = substr($head, 4, 2);
	return ($head_type == head_02_TypeKeepAlive_res ? 1 : 0);
}
function sn_head_is_TypeReport_res(&$head)
{
    $head_type = substr($head, 4, 2);
	return ($head_type == head_02_TypeReport_res ? 1 : 0);
}
function sn_head_is_TypeCtrl_req(&$head)
{
    $head_type = substr($head, 4, 2);
	return ($head_type == head_02_TypeCtrl_req ? 1 : 0);
}

function sn_im_sock_available()
{
    global $sn_tcp_ac_pid, $sn_tcp_id;
    return pid_ioctl($sn_tcp_ac_pid[$sn_tcp_id], "get rxlen");
}

function sn_recv_packet($timeout)
{
    global $sn_tcp_ac_pid, $sn_tcp_id;

    while(1)
    {
        if($timeout <= 0 && sn_im_sock_available() <= 0)
            return 0;

        if(sn_im_sock_available() <= 0)
        {
            $prev_tick = im_get_tick();
            while(pid_ioctl($sn_tcp_ac_pid[$sn_tcp_id], "get rxlen") <= 0)
            {
                $current_tick = im_get_tick();
                if($current_tick - $prev_tick >= $timeout)
                {
                    error_log("sn_recv_packet: timeout.");
                    return -1;
                }
            }
        }

        $packet_length = sn_read_length();
        if($packet_length < IM_PACKET_HEAD_LEN || $packet_length > 2048)
        {
            error_log("sn_recv_packet: illegal packet length.");
            sn_im_sock_flush();
            return -1;
        }

        $rbuf = "";
        $read = sn_im_sock_recv($rbuf, IM_PACKET_HEAD_LEN);
        if($read < IM_PACKET_HEAD_LEN)
        {
            error_log("sn_recv_packet: fail to reading.");
            sn_im_sock_flush();
            return -1;
        }

        $body_length = $packet_length - IM_PACKET_HEAD_LEN;
        if(sn_head_is_TypeDevAuth_res($rbuf))
        {
            error_log("sn_recv_packet: DevAuth_res");
            sn_read_DevAuth_res_body($body_length);
        }
        else if(sn_head_is_TypeKeepAlive_res($rbuf))
        {
            error_log("sn_recv_packet: TypeKeepAlive_res");
            sn_read_KeepAlive_res_body($body_length);
        }
        
        if(sn_im_sock_available() <= 0)
        {
            error_log("sn_recv_packet: no more packet.");
            return 0;
        }
    }

    return 0;
}

function sn_im_auth_device()
{
    global $sn_auth_success;
    global $sn_device_id, $sn_device_password, $sn_gateway_id;
    $body_length = sn_body_sizeof_devAuth($sn_gateway_id, $sn_device_id, $sn_device_password);
    $packet_length = IM_PACKET_HEAD_LEN + $body_length;

    $sent = 0;
    // write length of packet.
    sn_write_length($packet_length);
    // header
    $sent += sn_head_send_auth_device();
    // body
    $sent += sn_body_send_devAuth($sn_gateway_id, $sn_device_id, $sn_device_password);

    if($sent != $packet_length)
    {
        error_log("sn_im_auth_device: fail to send auth data.");
        return -1;
    }

    sn_recv_packet(IM_READ_TIMEOUT_SEC * 1000);

    if($sn_auth_success != 1)
    {
        error_log("sn_im_auth_device: fail to authentication.");
        return -1;
    }

    return 0;
}

function sn_im_send_keepalive()
{
    $packet_length = IM_PACKET_HEAD_LEN;
    $sent = 0;
    // write length of packet.
    sn_write_length($packet_length);
    // header
    $sent += sn_head_send_keepalive();
    if($sent != $packet_length)
    {
        error_log("sn_im_send_keepalive: fail to send auth data.");
        return -1;
    }

    sn_recv_packet(IM_READ_TIMEOUT_SEC * 1000);
}

$sn_keep_alive_sent_tick = 0;

function im_loop()
{
    global $sn_tcp_ac_pid, $sn_tcp_id, $sn_auth_success, $sn_keep_alive_sent_tick;

    if(im_tcp_connected() == TCP_CLOSED)
    {
        $sn_auth_success = 0;
        $keep_alive_sent_tick = 0;
        error_log(sprintf("Connected to %s:%d", IM_SERVER_IP, IM_SERVER_PORT));
        tcp_client($sn_tcp_id, IM_SERVER_IP, IM_SERVER_PORT);
    }
    else if(im_tcp_connected() == TCP_CONNECTED)
    {
        if($sn_auth_success != 1)
        {
            sn_im_auth_device();
            return;
        }

        //--------------------------------------------
        // Keepalive.
        if($sn_keep_alive_sent_tick == 0)
        {
            $sn_keep_alive_sent_tick = im_get_tick();
        }
        else
        {   
            $current_tick = im_get_tick();
            if($current_tick - $sn_keep_alive_sent_tick > (IM_KEEPALIVE_PERIODIC_SEC * 1000))
            {
                $sn_keep_alive_sent_tick = $current_tick;
                sn_im_send_keepalive();
            }

        }    
        //--------------------------------------------

        //--------------------------------------------
        // Receive data.
        sn_recv_packet(0);
        //--------------------------------------------
    }
}

?> 