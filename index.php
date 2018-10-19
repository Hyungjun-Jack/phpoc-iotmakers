<?php
set_time_limit(30);

include_once "/lib/sc_envs.php";
include_once "system.php";

$web_password = find_admin_password();

if($web_password != "")
{
  $auth = _SERVER("HTTP_AUTHORIZATION");

  if($auth)
  {
    $input_password = str_replace("Basic ", "", $auth);
    $input_password_dec = explode(":", system("base64 dec %1", $input_password)); 

    if($input_password_dec[1] != $web_password)
    {
      send_401();
      return;
    }
  }
  else
  {
    send_401();
    return;
  }
}

define("IP6_DHCP_DNS", 0x00000a0a);

$envs = envs_read();

//================================================
$ssid_env = envs_find($envs, ENV_CODE_WLAN, 0x01);
$ssid_pos = strpos($ssid_env, int2bin(0x00, 1));


$shared_key_env = envs_find($envs, ENV_CODE_WLAN, 0x08);	
$shared_key_pos = strpos($shared_key_env, int2bin(0x00, 1));
//================================================

//================================================
// WLAN STATUS.
$wlan_status = "";
$device_ip_address = "";
$device_6_addr = "";
$emac_id = "";

if(ini_get("init_net1") == "1")
{
  $pid_net1 = pid_open("/mmap/net1", O_NODIE);
  if($pid_net1 != -EBUSY && $pid_net1 != -ENOENT)
  {
    $wlan_status = pid_ioctl($pid_net1, "get mode");
    $emac_id = pid_ioctl($pid_net1, "get hwaddr");
    $emac_id = str_replace(":", "", $emac_id);
    $emac_id = substr($emac_id, 6);
    
    if($wlan_status != "")
    {
      $device_ip_address = pid_ioctl($pid_net1, "get ipaddr");
      $device_6_addr = pid_ioctl($pid_net1, "get ipaddr6");
    }
    pid_close($pid_net1);
  }
}

if($wlan_status == "")
{
  $pid_net0 = pid_open("/mmap/net0", O_NODIE);
  if($pid_net0 != -EBUSY && $pid_net0 != -ENOENT)
  {
    $device_ip_address = pid_ioctl($pid_net0, "get ipaddr");
    pid_close($pid_net0);
  }
}

// Admin settings.

//================================================

?>
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>IoTMakers</title>
        <link rel="stylesheet" type="text/css" href="style.css">
        <script>
            var headerMenu;
            var headerOffsetTop;
            window.addEventListener('load', function(){

                headerMenu = document.getElementById("Header__Menu");
                headerOffsetTop = headerMenu.offsetTop;
                window.onscroll =  function () {onScroll()};

                //------------------------------------------------------------------------
                // Internet
                if(document.body.getAttribute('data-ipv4-type') == 0)
                    document.getElementById("ipv4_static").checked = true;
                else
                    document.getElementById("ipv4_dhcp").checked = true;

                document.getElementById("4_addr").value = document.body.getAttribute('data-ipv4-address');
                document.getElementById("4_subnet").value = document.body.getAttribute('data-ipv4-subnet-mask');
                document.getElementById("4_gateway").value = document.body.getAttribute('data-ipv4-gateway');
                document.getElementById("4_dns").value = document.body.getAttribute('data-ipv4-dns');

                if(document.body.getAttribute('data-ipv6-enable') == 1)
                    document.getElementById("6_enable").checked = true;
                else
                    document.getElementById("6_disable").checked = true;

                if(document.body.getAttribute('data-ipv6-type') == 1)
                    document.getElementById("ip6_static").checked = true;
                else
                    document.getElementById("ip6_dhcp").checked = true;

                document.getElementById("6_eui").value = document.body.getAttribute('data-ipv6-eui');
                document.getElementById("6_addr").value = document.body.getAttribute('data-ipv6-address');
                document.getElementById("6_prefix").value = document.body.getAttribute('data-ipv6-prefix');
                document.getElementById("6_gateway").value = document.body.getAttribute('data-ipv6-gateway');
                document.getElementById("6_dns").value = document.body.getAttribute('data-ipv6-dns');

                if(document.body.getAttribute('data-wlan-enable') == 1)
                    document.getElementById("w_enable").checked = true;
                else
                    document.getElementById("w_disable").checked = true;

                switch(document.body.getAttribute('data-wlan-type'))
                {
                    case "0":
                        document.getElementById("wlan_adhoc").checked = true;
                    break;
                    case "1":
                        document.getElementById("wlan_infrastructure").checked = true;
                    break;
                    case "2":
                        document.getElementById("wlan_soft_ap").checked = true;
                    break;
                }
                
                document.getElementById("channel").value = document.body.getAttribute('data-wlan-channel');
                document.getElementById("ssid").value = document.body.getAttribute('data-wlan-ssid');
                document.getElementById("ssid_raw").value = document.body.getAttribute('data-wlan-ssid-raw');
                document.getElementById("shared_key").value = document.body.getAttribute('data-wlan-shared-key');

                switch(document.body.getAttribute('data-wlan-phy-mode'))
                {
                    case "0":
                        document.getElementById("phy_auto").checked = true;
                    break;
                    case "1":
                        document.getElementById("phy_802_11").checked = true;
                    break;
                    case "2":
                        document.getElementById("phy_802_11b").checked = true;
                    break;
                    case "3":
                        document.getElementById("phy_802_11bg").checked = true;
                    break;
                }
                
                if(document.body.getAttribute('data-wlan-short-preamble') == 1)
                    document.getElementById("preamble").checked = true;
                
                if(document.body.getAttribute('data-wlan-short-slot') == 1)
                    document.getElementById("slot").checked = true;
                
                if(document.body.getAttribute('data-wlan-cts-protection') == 1)
                    document.getElementById("cts").checked = true;

                // Admin

                //------------------------------------------------------------------------

                document.getElementById("button_save").addEventListener("click", onClickSubmit);
                document.getElementById("button_restart").addEventListener("click", onClickRestart);

                onClickIpv4Type();
                onClickIpv6Enable();
                onClickWlanEnable();
                onClickWlanPhyMode();

                setInternetState();
                setWlanState();
                setAdminSettings();

                document.getElementById("Home__Menu__Open").click();
                document.getElementById("Ipv4__Network__Open").click();
                document.getElementById("Admin__Open").click();
            });

            function onScroll() {
                if(window.pageYOffset >= headerOffsetTop) {
                    headerMenu.classList.add("Menu__Fixed");
                } else {
                    headerMenu.classList.remove("Menu__Fixed");
                }
            }

            function onClickMenuIcon() {
                
                if (headerMenu.classList.contains("Icon__Click")) {
                    headerMenu.classList.remove("Icon__Click")
                } else {
                    headerMenu.classList.add("Icon__Click")
                }
            }
            function onClickRestart()
            {
                if(!confirm("시스템을 다시 시작하시겠습니까?"))
                    return;
            
                var xhttp = makeXHTTP();
            
                document.getElementById("Activity__Indicator").style.display = "block";
            
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4)
                    {
                        document.getElementById("Activity__Indicator").style.display = "none";
                        
                        if(this.status == 200) 
                        {
                            console.log(this.responseText);
                            alert("시스템 재시작 요청을 보냈습니다. 잠시 후 다시 접속해보세요.");
                        }
                        else if(this.status >= 100)
                        {
                            var msg = "[" + this.status + "] " + this.statusText;
                            alert(msg);
                        }
                    }
                };
            
                xhttp.onerror = function(e) {
                    alert("[네트워크 에러] 서버와 통신할 수 없습니다.");
                };
            
                xhttp.ontimeout = function() {    
                    alert("[네트워크 타임아웃] 서버와 통신할 수 없습니다.");
                };
            
                xhttp.open("GET", "restart.php?t=" + Math.random(), true);
                xhttp.send();
            }
            function onClickSubmit()
            {
                if(!confirm("변경사항을 저장하시겠습니까?"))
                    return;

                if((x = validCheckIPv4()).length != 0)
                {
                    document.getElementById("Network__Menu__Open").click();
                    document.getElementById("Ipv4__Network__Open").click();
                    x[0].focus();
                    return;
                }

                <?php
                if(ini_get("init_ip6") === "1")
                {
                    echo "if((x = validCheckIPv6()).length != 0)\r\n";
                    echo "{\r\n";
                    echo "  document.getElementById(\"Network__Menu__Open\").click();\r\n";
                    echo "  document.getElementById(\"Ipv6__Network__Open\").click();\r\n";
                    echo "  x[0].focus();\r\n";
                    echo "  return;\r\n";
                    echo "}\r\n";
                }
                
                if(ini_get("init_net1") === "1")
                {
                    echo "if((x = validCheckWlan()).length != 0)\r\n";
                    echo "{\r\n";
                    echo "  document.getElementById(\"Network__Menu__Open\").click();\r\n";
                    echo "  document.getElementById(\"Wlan__Network__Open\").click();\r\n";
                    echo "  x[0].focus();\r\n";
                    echo "  return;\r\n";
                    echo "}\r\n";
                }
                ?>

                if((x = validCheckAdmin()).element.length != 0)
                {
                    document.getElementById("Admin__Menu__Open").click();
                    document.getElementById(x.menu_name + "__Open").click();
                    x.element[0].focus();
                    return;
                }

                document.getElementById("network_setup").target = "submit_target";
                document.getElementById("network_setup").submit();

                document.getElementById("Activity__Indicator").style.display = "block";
            }

            function network_setup_finish()
            {
                document.getElementById("admin_setup").target = "submit_target";
                document.getElementById("admin_setup").submit();
            }

            function admin_setup_finish()
            {
                setAdminSettings();
                document.getElementById("Activity__Indicator").style.display = "none";
            }
        </script>
    </head>
    <body>
        <div id="Activity__Indicator" class="Activity__Indicator">
            <div class="Loader Small Big">
                <div id="Loader__Blade" class="Loader__Blade Small Big">
                <div class="Blade__Center Small Big">
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                    <div class="Blade Small Big"></div>
                </div>
                </div>
            </div>
        </div>
        <div class="Header">
            <div class="Header__Title">
                <h1><? echo system("uname -i") ?></h1>
            </div>
            <div class="Header__Menu" id="Header__Menu">
                <button type="button" class="Menu__Icon" onclick="onClickMenuIcon()">&#9776;</button>
                <button type="button" class="Menu__Links" onclick="openMenu(event, 'Home')" id="Home__Menu__Open">홈</button>
                <button type="button" class="Menu__Links" onclick="openMenu(event, 'Network__Settings')" id="Network__Menu__Open">인터넷</button>
                <button type="button" class="Menu__Links" onclick="openMenu(event, 'Admin__Settings')" id="Admin__Menu__Open">관리자</button>
            </div>
        </div>
        <div id="Home" class="MenuContent">
            <div class="Board">
                <div class="Board__Column">
                    <button class="Folding__Button" onClick="foldingButton(this)">인터넷</button>
                    <div class="Folding__Content">
                        <div class="Board__Title">IPv4</div>
                        <div class="Board__Text" id='IP4__Type'></div>
                        <div class="Board__Text" id='IP4__State'></div>

                        <div class="Board__Title">IPv6</div>
                        <div class="Board__Text" id='IP6__Type'></div>
                        <div class="Board__Text" id='IP6__State'></div>

                        <div class="Board__Title">WLAN</div>
                        <div class="Board__Text" id='Wlan__Type'></div>
                        <div class="Board__Text" id='Wlan__State1'></div>

                        <div class="Board__Title">기타</div>
                        <div class="Board__Text" id='Etc__Polling'></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="Action__Buttons" class="MenuContent Action__Buttons">
            <iframe src="" height="0" width="0" style="border:none;" name="submit_target"></iframe>  
            <button type="button" class="Action" id="button_save">변경사항 저장</button>
            <button type="button" class="Action" id="button_restart">시스템 다시시작</button>
        </div>

        <div id="Network__Settings" class="MenuContent Network">
            <form id="network_setup" action="network_setup.php" method="post">
                <div class="Vertical">
                    <div class="Vertical__Menu Network">
                        <button type="button" class="Network__Button" onclick="openSubMenu('Vertical__Content Network', 'Network__Button', event, 'Ipv4')" id="Ipv4__Network__Open">IPv4</button>
                        <button type="button" class="Network__Button" onclick="openSubMenu('Vertical__Content Network', 'Network__Button', event, 'Ipv6')" id="Ipv6__Network__Open">IPv6</button>
                        <button type="button" class="Network__Button" onclick="openSubMenu('Vertical__Content Network', 'Network__Button', event, 'Wlan')" id="Wlan__Network__Open">무선랜</button>
                    </div>
                    <div id="Ipv4" class="Vertical__Content Network">
                        <div class="Title">IPv4</div>
                        <div class="Subtitle">
                            <input type="radio" value="1" name="4_type" onclick="onClickIpv4Type();" id="ipv4_dhcp"> 자동으로 IP 주소 받기<br>
                            <input type="radio" value="0" name="4_type" onclick="onClickIpv4Type();" id="ipv4_static"> 고정된 IP 주소 사용
                        </div>
                        <div class="Title">제품 IPv4 주소</div>
                        <div class="Subtitle">
                            <input type="text" class="style-text" name="4_addr" id="4_addr" size="18" maxlength="15">
                            <div id="4_addr_error" class="Error__Text"></div>
                        </div>

                        <div class="Title">서브넷 마스크</div>
                        <div class="Subtitle">
                            <input type="text" class="style-text" name="4_subnet" id="4_subnet" size="18" maxlength="15">
                            <div id="4_subnet_error" class="Error__Text"></div>
                        </div>

                        <div class="Title">게이트웨어 IPv4 주소</div>
                        <div class="Subtitle">
                            <input type="text" class="style-text" name="4_gateway" id="4_gateway" size="18" maxlength="15">
                            <div id="4_gateway_error" class="Error__Text"></div>
                        </div>

                        <div class="Title">DNS 서버 IPv4 주소</div>
                        <div class="Subtitle">
                            <input type="text" class="style-text" name="4_dns" id="4_dns" size="18" maxlength="15"><br>
                            <div id="4_dns_error" class="Error__Text"></div>
                            <input type="checkbox" name="v4_dhcp_dns" id="v4_dhcp_dns" onclick="onClickIpv4DhcpDns();" value="1">DNS 서버 IP주소 수동입력
                        </div>
                    </div>
                    <div id="Ipv6" class="Vertical__Content Network">
                        <div class="Title">IPv6</div>
                        <div class="Subtitle">
                            <input type="radio" name="6_enable" id="6_enable" value="1" onclick="onClickIpv6Enable();">사용
                            <input type="radio" name="6_enable" id="6_disable" value="0" onclick="onClickIpv6Enable();">사용 안 함
                        </div>

                        
                        <div class="Subtitle">
                            <input type="radio" name="6_type" id="ip6_dhcp" onclick="onClickIpv6Type();" value="0"> 자동으로 IP 주소 받기<br>
                            <input type="radio" name="6_type" id="ip6_static" onclick="onClickIpv6Type();" value="1"> 고정된 IP 주소 사용
                        </div>

                        <div class="Title">EUI</div>
                        <div class="Subtitle">
                            <select name="6_eui" id="6_eui">
                                <option value="0">MAC 주소</option>
                                <option value="1">Random</option>
                            </select>
                        </div>

                        <div class="Title">제품 IPv6 주소</div>
                        <div class="Subtitle">
                            <input type="text" class="style-text" name="6_addr" id="6_addr" size="30" maxlength="39"> 
                            / 
                            <div id="6_addr_error" class="Error__Text"></div>
                            <input type="number" style="width:40px;" name="6_prefix" id="6_prefix">
                            <div id="6_prefix_error" class="Error__Text"></div>
                        </div>

                        <div class="Title">게이트웨이 IPv6 주소</div>
                        <div class="Subtitle">
                            <input type="text" class="style-text" name="6_gateway" id="6_gateway" size="30" maxlength="39">
                        </div>

                        <div class="Title">DNS 서버 IPv6 주소</div>
                        <div class="Subtitle">
                            <input type="text" class="style-text" name="6_dns" id="6_dns" size="30" maxlength="39">
                            <div id="6_dns_error" class="error"></div>
                            <input type="checkbox" name="6_dhcp_dns" id="6_dhcp_dns" onclick="onClickIpv6DhcpDns();" value="1">DNS 서버 IP주소 수동입력
                        </div>
                    </div>
                    <div id="Wlan" class="Vertical__Content Network">
                        <div class="Title">무선랜</div>
                        <div class="Subtitle">
                            <input type="radio" name="w_enable" id="w_enable" value="1" onclick="onClickWlanEnable();">사용
                            <input type="radio" name="w_enable" id="w_disable" value="0" onclick="onClickWlanEnable();">사용 안 함
                        </div>

                        <div class="Title">무선랜 종류</div>
                        <div class="Subtitle">
                            <input type="radio" name="w_type" id="wlan_adhoc" value="0" onclick="onClickWlanType();">애드혹<br>
                            <input type="radio" name="w_type" id="wlan_infrastructure" value="1" onclick="onClickWlanType();">인프라스트럭쳐&nbsp;
                            <button type="button" name="wlan_search_ap" id="wlan_search_ap" class="Black__Button" onclick="onClickSearchAP();">AP 검색</button><br>
                            <div id="ap_list" class="List__Collapse">
                                <table id="ap_list_table" class="Table__List">
                                <tr class="Bottom__Line">
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td style="width:60px;">
                                        <button type="button" class="Black__Button" onclick="onClickCloseList('ap_list', 'ap_list_table');">닫기</button>
                                    </td>
                                </tr>
                                </table>
                            </div>
                            <input type="radio" name="w_type" id="wlan_soft_ap" value="2" onclick="onClickWlanType();">Soft AP
                        </div>
                        <div class="Title">채널</div>
                        <div class="Subtitle">
                            <select name="channel" id="channel">
                                <?php
                                for($i = 0;$i <= 13;$i++)
                                {
                                echo "<option value=\"$i\">";
                                $channel_value = $i == 0 ? "자동" : $i;
                                echo "$channel_value</option>\r\n";
                                }
                                ?>
                            </select>
                            <button type="button" class="Black__Button" name="wlan_search_channel" id="wlan_search_channel" onclick="onClickSearchChannel();">채널 검색</button>
                            <div id="channel_list" class="List__Collapse">
                                <table id="channel_list_table" class="Table__List">
                                <tr class="Bottom__Line">
                                    <td>&nbsp;</td>
                                    <td style="width:60px;">
                                        <button type="button" class="Black__Button" onclick="onClickCloseList('channel_list', 'channel_list_table');">닫기</button>
                                    </td>
                                </tr>
                                </table>
                            </div>
                        </div>
                        <div class="Title">SSID</div>
                        <div class="Subtitle">
                            <input type="text" class="style-text" name="ssid" id="ssid" maxlength="32">
                            <input type="hidden" name="ssid_raw" id="ssid_raw">
                            <div id="ssid_error" class="error"></div>
                        </div>
                        <div class="Title">Shared Key</div>
                        <div class="Subtitle">
                            <input type="password" class="style-text" name="shared_key" id="shared_key"><br>
                            <input type="checkbox" name="hide_shared_key" id="hide_shared_key" onclick="onClickHideWlanSharedKey()" checked>문자 숨기기
                        </div>

                        <div class="Title">무선 고급 설정</div>
                        <div class="Subtitle">
                            <button type="button" class="Black__Button" name="wlan_advanced" id="wlan_advanced" onclick="onClickAdvancedOption();">무선 고급 설정</button>
                            <div id="advanced_option_list" class="List__Collapse">
                                <table id="advanced_option_list_table" class="Table__List">
                                <tr class="Bottom__Line">
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td style="width:60px;">
                                        <button type="button" class="Black__Button" onclick="onClickCloseList('advanced_option_list', 'advanced_option_list_table', false);">닫기</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td rowspan=4 class="Bottom__Line" style="width:75px;">Phy Mode</td>
                                    <td colspan=2>
                                    <input type="radio" name="phy_mode" id="phy_auto" onclick="onClickWlanPhyMode();" value="0"> 자동
                                    </td>
                                </tr>
                                <tr>  
                                    <td colspan=2>
                                    <input type="radio" name="phy_mode" id="phy_802_11" onclick="onClickWlanPhyMode();" value="1"> 802.11
                                    </td>
                                </tr>
                                <tr>  
                                    <td colspan=2>
                                    <input type="radio" name="phy_mode" id="phy_802_11b" onclick="onClickWlanPhyMode();" value="2"> 802.11b
                                    </td>
                                </tr>
                                <tr class="Bottom__Line">
                                    <td colspan=2>
                                    <input type="radio" name="phy_mode" id="phy_802_11bg" onclick="onClickWlanPhyMode();" value="3"> 802.11b/g
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan=3>
                                    <input type="checkbox" name="preamble" id="preamble" value="1"> Short Preamble
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan=3>
                                    <input type="checkbox" name="slot" id="slot" value="1"> Short Slot
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan=3>
                                    <input type="checkbox" name="cts" id="cts" value="1"> CTS Protection
                                    </td>
                                </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div id="Admin__Settings" class="MenuContent Admin">
            <form id="admin_setup" action="admin_setup.php" method="post">
                <div class="Vertical">
                    <div class="Vertical__Menu Admin">
                        <button type="button" class="Admin__Button" onclick="openSubMenu('Vertical__Content Admin', 'Admin__Button', event, 'Admin')" id="Admin__Open">관리자계정</button>
                    </div>
                    <div id="Admin" class="Vertical__Content Admin">
                        <div class="Title">관리자 계정</div>
                        <div class="Subtitle">admin</div>
                        <div class="Title">관리자 비밀번호</div>
                        <div class="Subtitle">
                            <input type="password" name="admin_pwd" id="admin_pwd" size="8" maxlength="8" value="<? echo $web_password ?>">&nbsp;(4~8자)<br>
                            <input type="checkbox" id="hide_admin_pwd" onclick="onClickHideAdminPwd()" checked>문자 숨기기
                            <div id="admin_pwd_error" class="Error__Text"></div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
            function openMenu(evt, menu) {
                var i, menuContent, menuLinks;
                menuContent = document.getElementsByClassName("MenuContent");
                for(i = 0;i < menuContent.length;i++){
                    menuContent[i].style.display = "none";
                }
                
                document.getElementById(menu).style.display = "block";

                var headerMenu = document.getElementById("Header__Menu");
                if (headerMenu.classList.contains("Icon__Click")) {
                    headerMenu.classList.remove("Icon__Click")
                }

                document.getElementById("Action__Buttons").style.display = (menu == "Home" ? "none" : "block");
            }

            function openSubMenu(menuClassName, buttonClassName, evt, divId){
                divs = document.getElementsByClassName(menuClassName);
                for(i = 0; i < divs.length; i++) {
                    divs[i].style.display = "none";
                }
                buttons = document.getElementsByClassName(buttonClassName);
                for(i = 0; i < buttons.length; i++) {
                    buttons[i].classList.remove("Active");
                }
                document.getElementById(divId).style.display = "block";
                evt.currentTarget.classList.add("Active");
            }

            function foldingButton(obj) {
                obj.classList.toggle("Closed");
                var content = obj.nextElementSibling;

                if(content != null){
                    if(content.style.maxHeight == "" || content.style.maxHeight != "0px")
                        content.style.maxHeight = "0px";
                    else
                        content.style.maxHeight = content.scrollHeight + "px";
                }
            }

            document.body.setAttribute('data-ipv4-type', <? echo envs_get_net_opt($envs, NET_OPT_DHCP) ?>);
            document.body.setAttribute('data-ipv4-address', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x00), 0, 4)) ?>");
            document.body.setAttribute('data-ipv4-subnet-mask', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x01), 0, 4)) ?>");
            
            document.body.setAttribute('data-ipv4-gateway', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x02), 0, 4)) ?>");
            document.body.setAttribute('data-ipv4-dns', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x03), 0, 4)) ?>");
            document.body.setAttribute('data-ipv4-dhcp-dns', <? echo envs_get_net_opt($envs, NET_OPT_AUTO_NS) ?>);
            
            document.body.setAttribute('data-ipv6-enable', <? echo envs_get_net_opt($envs, NET_OPT_IP6) ?>);
            document.body.setAttribute('data-ipv6-type', <? echo envs_get_net_opt($envs, NET_OPT_IP6_GUA) ?>);
            document.body.setAttribute('data-ipv6-dhcp-dns', <? echo envs_get_net_opt($envs, IP6_DHCP_DNS) ?>);
            document.body.setAttribute('data-ipv6-eui', <? echo envs_get_net_opt($envs, NET_OPT_IP6_EUI) ?>);
            document.body.setAttribute('data-ipv6-address', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP6, 0x00), 0, 16)) ?>");
            document.body.setAttribute('data-ipv6-prefix', "<? echo bin2int(substr(envs_find($envs, ENV_CODE_IP6, 0x00), 16, 2), 0, 2) ?>");
            document.body.setAttribute('data-ipv6-gateway', "<? echo inet_ntop(substr(envs_find($envs, ENV_CODE_IP6, 0x02), 0, 16)) ?>");
            document.body.setAttribute('data-ipv6-dns', "<? inet_ntop(substr(envs_find($envs, ENV_CODE_IP6, 0x03), 0, 16)) ?>");
            
            document.body.setAttribute('data-wlan-status', "<? echo $wlan_status ?>");
            document.body.setAttribute('data-wlan-enable', <? echo envs_get_net_opt($envs, NET_OPT_WLAN) ?>);
            document.body.setAttribute('data-wlan-type', <? echo envs_get_net_opt($envs, NET_OPT_TSF) ?>);
            document.body.setAttribute('data-wlan-channel', <? echo envs_get_net_opt($envs, NET_OPT_CH) ?>);
            document.body.setAttribute('data-wlan-ssid', "<? echo substr($ssid_env, 0, (int)$ssid_pos) ?>");
            document.body.setAttribute('data-wlan-emac-id', "<? echo $emac_id ?>");
            document.body.setAttribute('data-wlan-ssid-raw', "<? echo bin2hex(substr($ssid_env, 0, (int)$ssid_pos)) ?>");
            document.body.setAttribute('data-wlan-shared-key', "<? echo substr($shared_key_env, 0, (int)$shared_key_pos) ?>");
            document.body.setAttribute('data-wlan-phy-mode', <? echo envs_get_net_opt($envs, NET_OPT_PHY) ?>);
            document.body.setAttribute('data-wlan-short-preamble', <? echo envs_get_net_opt($envs, NET_OPT_SHORT_PRE) ?>);
            document.body.setAttribute('data-wlan-short-slot', <? echo envs_get_net_opt($envs, NET_OPT_SHORT_SLOT) ?>);
            document.body.setAttribute('data-wlan-cts-protection', <? echo envs_get_net_opt($envs, NET_OPT_CTS_PROT) ?>);

            function setInternetState(){
                var internet_status = "";
                if(document.body.getAttribute('data-ipv4-type') == 0)
                {
                    document.getElementById("IP4__Type").innerHTML = "고정 IP 연결입니다."
                }
                else
                {
                    document.getElementById("IP4__Type").innerHTML = "동적 IP 연결입니다."
                }
                document.getElementById("IP4__State").innerHTML = "주소는 <? echo $device_ip_address?>입니다.";


                if(document.body.getAttribute('data-ipv6-enable') == 1)
                {
                    if(document.body.getAttribute('data-ipv6-type') == 1)
                        document.getElementById("IP6__Type").innerHTML = "고정 IP 연결입니다."
                    else
                        document.getElementById("IP6__Type").innerHTML = "동적 IP 연결입니다."

                    document.getElementById("IP6__State").innerHTML = "주소는 <? echo $device_6_addr?>입니다.";
                }
                else
                {
                    document.getElementById("IP6__Type").style.display = "none";
                    document.getElementById("IP6__State").innerHTML = "IPv6는 사용 중이 아닙니다.";
                }
            }

            function setWlanState(){
                var wlan_status;
                if(document.body.getAttribute('data-wlan-status') == "")
                {
                    document.getElementById("Wlan__Type").style.display = "none";
                    document.getElementById("Wlan__State1").innerHTML = "무선은 연결이 안 되어있습니다.";
                }
                else
                {   
                    wlan_status = "무선랜 종류는 ";
                    switch(document.body.getAttribute('data-wlan-status'))
                    {
                    case "INFRA":
                        wlan_status += "인프라스트럭처";
                        break;
                    case "IBSS":
                        wlan_status += "애드혹";
                        break;
                    case "AP":
                        wlan_status += "Soft AP";
                        break;
                    }
                    wlan_status += "입니다.";
                    document.getElementById("Wlan__Type").innerHTML = wlan_status;
                    wlan_status = "SSID는 " + document.body.getAttribute('data-wlan-ssid') + "입니다.";
                    wlan_status = wlan_status.replace("$emac_id", document.body.getAttribute('data-wlan-emac-id'));
                    document.getElementById("Wlan__State1").innerHTML = wlan_status;
                }
            }

            function setAdminSettings(){
                var text = "";
                
                document.getElementById("Etc__Polling").innerHTML = text;
            }

            function onClickHideAdminPwd()
            {
                if(document.getElementById("hide_admin_pwd").checked == true)
                    document.getElementById("admin_pwd").type = "password";
                else
                    document.getElementById("admin_pwd").type = "text";
            }

            function validCheckAdmin()
            {
                var x = {};
                x.menu_name = "";
                x.element = [];
                
                document.getElementById("admin_pwd_error").innerHTML = "";
                element = document.getElementById("admin_pwd");
                element.value = element.value.trim();
                if(element.value == "" || element.value.length < 4 || element.value.length > 8)
                {
                    document.getElementById("admin_pwd_error").innerHTML = "관리자 비밀번호를 4~8자로 입력하세요.";
                    x.element.push(element);
                    if(x.menu_name == "")
                        x.menu_name = "Admin";
                }
                return x;
            }

            function onClickSearchAP()
            {
                var xhttp = makeXHTTP();

                document.getElementById("Activity__Indicator").style.display = "block";

                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4)
                    {
                        var ap_list_table = document.getElementById("ap_list_table");
                    
                        if(document.getElementById("ap_list").className != "List__Expand")
                        {
                            document.getElementById("ap_list").className = "List__Expand";
                            document.getElementById("Wlan").classList.add("Expand");
                        }
                    
                        document.getElementById("Activity__Indicator").style.display = "none";
                    
                        if(this.status == 200) 
                        {
                            console.log(this.responseText);
                            var response = JSON.parse(this.responseText);
                            
                            if(response.status == "")
                            {
                                var row = ap_list_table.insertRow(1);
                                row.className = "Bottom__Line";
                                var cell0 = row.insertCell(0);
                                cell0.colSpan = 3;
                                cell0.innerHTML = "<div class='Error__Text'>무선랜을 사용할 수 없습니다.</div>";
                            }
                            else
                            {
                            var number_of_ap = response.item_count;
                            if(number_of_ap > 0)
                            {
                                for(var idx = 0;idx < number_of_ap;idx++)
                                {
                                var ap = response.ap[idx];
                                console.log(ap.ssid + ap.security + ap.rssi + ap.ssid_raw);
                                var row = ap_list_table.insertRow(1 + idx);
                                row.className = "Bottom__Line";
                                
                                var cell0 = row.insertCell(0);
                                var cell1 = row.insertCell(1);
                                var cell2 = row.insertCell(2);
                                //var cell3 = row.insertCell(3);
                                
                                var html = "";
                                html = "<div class=\"Wifi__Strength";
                                
                                if(ap.rssi <= 50)
                                {
                                    html += " S4\">";
                                }
                                else if(ap.rssi > 50 && ap.rssi <= 60)
                                {
                                    html += " S3\">";
                                }
                                else if(ap.rssi > 60 && ap.rssi <= 70)
                                {
                                    html += " S2\">";
                                }
                                else if(ap.rssi > 70)
                                {
                                    html += " S1\">";
                                }
                                html += "<div class=\"Wifi__Security";
                                if(ap.security != "None")
                                    html += " Set";
                                html += "\"></div></div>";
                                cell0.innerHTML = html;
                                cell0.style = "width:16px;";
                                cell1.innerHTML = ap.ssid;
                                html = "<button type=\"button\" class=\"Black__Button\" onclick=\"onClickSelectAP('" + ap.ssid + "','" + ap.ssid_raw + "','" + ap.security + "');\">선택</button>";
                                cell2.innerHTML = html;
                                }
                            }
                            else
                            {
                                var row = ap_list_table.insertRow(1);
                                row.className = "Bottom__Line";
                                var cell0 = row.insertCell(0);
                                cell0.colSpan = 3;
                                cell0.innerHTML = "<div class='Error__Text'>검색된 AP가 없습니다.</div>";
                            }
                            }
                        }
                        else if(this.status >= 100)
                        {
                            var msg = "[" + this.status + "] " + this.statusText;
                            var row = ap_list_table.insertRow(1);
                            row.className = "Bottom__Line";
                            var cell0 = row.insertCell(0);
                            cell0.colSpan = 3;
                            cell0.innerHTML = msg;
                        }
                    }
                };
                
                xhttp.onerror = function(e) {
                    var msg = "[" + this.status + "] " + this.statusText;
                    var row = ap_list_table.insertRow(1);
                    var cell0 = row.insertCell(0);
                    row.className = "Bottom__Line";
                    cell0.colSpan = 3;
                    cell0.innerHTML = "[네트워크 에러] 서버와 통신할 수 없습니다.";
                };
                
                xhttp.ontimeout = function() {    
                    var msg = "[" + this.status + "] " + this.statusText;
                    var row = ap_list_table.insertRow(1);
                    var cell0 = row.insertCell(0);
                    row.className = "Bottom__Line";
                    cell0.colSpan = 3;
                    cell0.innerHTML = "[네트워크 타임아웃] 서버와 통신할 수 없습니다.";
                };
                
                xhttp.open("POST", "search_ap.php", true);
                xhttp.send();
                clearListTable("ap_list_table");
            }

            function onClickSearchChannel()
            {
                var xhttp = makeXHTTP();

                document.getElementById("Activity__Indicator").style.display = "block";

                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4)
                    {
                        var channel_list_table = document.getElementById("channel_list_table");
                        
                        if(document.getElementById("channel_list").className != "List__Expand")
                        {
                            document.getElementById("channel_list").className = "List__Expand";
                            document.getElementById("Wlan").classList.add("Expand");
                        }
                    
                        document.getElementById("Activity__Indicator").style.display = "none";
                    
                        if(this.status == 200)
                        {
                            console.log(this.responseText);
                            var response = JSON.parse(this.responseText);
                            
                            if(response.status == "")
                            {
                                var row = channel_list_table.insertRow(1);
                                row.className = "Bottom__Line";
                                var cell0 = row.insertCell(0);
                                cell0.colSpan = 2;
                                cell0.innerHTML = "<div class='Error__Text'>무선랜을 사용할 수 없습니다.</div>";
                            }
                            else
                            {
                                var number_of_channel = response.item_count;
                                for(var idx = 0;idx < number_of_channel - 1;idx++)
                                {
                                    var channel = response.channels[idx];
                                    var row = channel_list_table.insertRow(1 + idx);
                                    row.className = "Bottom__Line";
                                    
                                    var cell0 = row.insertCell(0);
                                    var cell1 = row.insertCell(1);
                                    
                                    var html = "<b>Channel " + channel.channel + "(" + channel.item_count + ")</b><br>";
                                    html += channel.ssid;
                                    
                                    cell0.innerHTML = html;
                                    cell1.innerHTML = "<button type=\"button\" class=\"Black__Button\" onclick=\"onClickSelectChannel('" + channel.channel + "')\">선택</button>";
                                }
                            }
                        }
                        else if(this.status >= 100)
                        {
                            var msg = "[" + this.status + "] " + this.statusText;
                            var row = channel_list_table.insertRow(1);
                            row.className = "Bottom__Line";
                            var cell0 = row.insertCell(0);
                            cell0.colSpan = 2;
                            cell0.innerHTML = msg;
                        }
                    }
                };
                
                xhttp.onerror = function() {
                    var row = channel_list_table.insertRow(1);
                    var cell0 = row.insertCell(0);
                    row.className = "Bottom__Line";
                    cell0.colSpan = 2;
                    cell0.innerHTML = "[네트워크 에러] 서버와 통신할 수 없습니다.";
                };
                
                xhttp.ontimeout = function() {    
                    var row = channel_list_table.insertRow(1);
                    var cell0 = row.insertCell(0);
                    row.className = "Bottom__Line";
                    cell0.colSpan = 2;
                    cell0.innerHTML = "[네트워크 타임아웃] 서버와 통신할 수 없습니다.";
                };
                
                xhttp.open("POST", "search_channel.php", true);
                xhttp.send();
                clearListTable("channel_list_table");

            }

            function onClickAdvancedOption()
            {
                var advanced_option_list_table = document.getElementById("advanced_option_list_table");
                var tabcontent = document.getElementById("Wlan");
                
                if(document.getElementById("advanced_option_list").className != "List__Expand")
                {
                    document.getElementById("advanced_option_list").className = "List__Expand";
                    document.getElementById("Wlan").classList.add("Expand");
                }
            }

            function makeXHTTP()
            {
                var xhttp;
                if (window.XMLHttpRequest) 
                    xhttp = new XMLHttpRequest();
                else 
                    xhttp = new ActiveXObject("Microsoft.XMLHTTP");

                return xhttp;
            }

            function clearListTable(table_id)
            {
                var list_table = document.getElementById(table_id);
                var rows = list_table.rows;
                if(rows.length > 1)
                {
                    var idx_max = rows.length - 1;
                    for(var idx = idx_max;idx >= 1;idx--)
                    {
                        list_table.deleteRow(idx);
                    }
                }
            }

            function onClickCloseList(div_id, table_id, erase)
            {
                erase = typeof erase !== 'undefined' ? erase : true;
                
                document.getElementById(div_id).className = "List__Collapse";
                
                if(erase)
                    clearListTable(table_id);

                document.getElementById("Wlan").classList.remove("Expand");
            }

            function onClickSelectAP(ssid, ssid_raw, security)
            {
                document.getElementById("ssid").value = ssid;
                document.getElementById("ssid_raw").value = ssid_raw;
                console.log(document.getElementById("ssid_raw").value);
                document.getElementById("shared_key").value = "";
            }

            function onClickSelectChannel(channel)
            {
                document.getElementById("channel").value = channel;
            }

            function onClickIpv4Type() 
            {
                if(document.getElementById("ipv4_dhcp").checked == true)
                {
                    document.getElementById("4_addr").disabled = true;
                    document.getElementById("4_subnet").disabled = true;
                    document.getElementById("4_gateway").disabled = true;
                    
                    document.getElementById("v4_dhcp_dns").disabled = false;
                    document.getElementById("v4_dhcp_dns").checked = document.body.dataset.ipv4DhcpDns == 1 ? false : true;
                    document.getElementById("4_dns").disabled = !document.getElementById("v4_dhcp_dns").checked;
                }
                else
                {
                    document.getElementById("4_addr").disabled = false;
                    document.getElementById("4_subnet").disabled = false;
                    document.getElementById("4_gateway").disabled = false;
                    document.getElementById("4_dns").disabled = false;
                    
                    document.getElementById("v4_dhcp_dns").disabled = true;
                    document.getElementById("v4_dhcp_dns").checked = false;
                }
                
                validCheckIPv4();
            }

            function validCheckIPv4()
            {
                var x = [];
                //--------------------------------------------------------
                // IPv4
                document.getElementById("4_addr_error").innerHTML = "";
                document.getElementById("4_subnet_error").innerHTML = "";
                document.getElementById("4_gateway_error").innerHTML = "";
                document.getElementById("4_dns_error").innerHTML = "";
                
                if(document.getElementById("ipv4_dhcp").checked == true)
                {
                    //dhcp
                    if(document.getElementById("v4_dhcp_dns").checked == true)
                    {
                        var element = document.getElementById("4_dns");
                        if(element.value.trim() == "")
                        {
                            document.getElementById("4_dns_error").innerHTML = "DNS 서버 IPv4 주소를 입력하세요.";
                            x.push(element);
                        }
                        else if(element.value.trim() != "" && !checkIpForm(element))
                        {
                            document.getElementById("4_dns_error").innerHTML = "DNS 서버 IPv4 주소가 올바르지 않습니다.";
                            x.push(element);
                        }
                    }
                }
                else
                {
                    //static
                    var element = document.getElementById("4_addr");
                    if(!checkIpForm(element))
                    {
                        document.getElementById("4_addr_error").innerHTML = "제품 IPv4 주소가 올바르지 않습니다.";
                        x.push(element);
                    }
                    
                    element = document.getElementById("4_subnet");
                    if(!checkIpForm(element))
                    {
                        document.getElementById("4_subnet_error").innerHTML = "서브넷 마스크가 올바르지 않습니다.";
                        x.push(element);
                    }
                    
                    element = document.getElementById("4_gateway");
                    if(element.value.trim() != "" && !checkIpForm(element))
                    {
                        document.getElementById("4_gateway_error").innerHTML = "게이트웨어 IPv4 주소가 올바르지 않습니다.";
                        x.push(element);
                    }
                    
                    element = document.getElementById("4_dns");
                    if(element.value.trim() != "" && !checkIpForm(element))
                    {
                        document.getElementById("4_dns_error").innerHTML = "DNS 서버 IPv4 주소가 올바르지 않습니다.";
                        x.push(element);
                    }
                }  
                //--------------------------------------------------------
                return x;
            }
            function onClickIpv6Enable() 
            {
                if(document.getElementById("6_enable").checked == true) 
                {
                    document.getElementById("ip6_dhcp").disabled = false;
                    document.getElementById("ip6_static").disabled = false;
                    
                    document.getElementById("6_eui").disabled = false;
                    
                    if(document.getElementById("ip6_dhcp").checked == true) 
                    {
                        document.getElementById("6_addr").disabled = true;
                        document.getElementById("6_prefix").disabled = true;
                        document.getElementById("6_gateway").disabled = true;
                        
                        document.getElementById("6_dhcp_dns").disabled = false;
                        document.getElementById("6_dhcp_dns").checked = document.body.dataset.ipv6DhcpDns == 1 ? false : true;
                        document.getElementById("6_dns").disabled = !document.getElementById("6_dhcp_dns").checked;;
                    } 
                    else if(document.getElementById("ip6_static").checked == true)
                    {
                        document.getElementById("6_addr").disabled = false;
                        document.getElementById("6_prefix").disabled = false;
                        document.getElementById("6_gateway").disabled = false;
                        document.getElementById("6_dns").disabled = false;
                        
                        document.getElementById("6_dhcp_dns").disabled = true;
                    }
                } 
                else 
                {
                    document.getElementById("ip6_dhcp").disabled = true;
                    document.getElementById("6_eui").disabled = true;
                    document.getElementById("ip6_static").disabled = true;
                    document.getElementById("6_addr").disabled = true;
                    document.getElementById("6_prefix").disabled = true;
                    document.getElementById("6_gateway").disabled = true;
                    document.getElementById("6_dns").disabled = true;
                    document.getElementById("6_dhcp_dns").disabled = true;
                }
                
                validCheckIPv6();
            }

            function validCheckIPv6()
            {
                var x = [];
                
                document.getElementById("6_addr_error").innerHTML = "";
                document.getElementById("6_prefix_error").innerHTML = "";
                document.getElementById("6_dns_error").innerHTML = "";
                
                if(document.getElementById("6_enable").checked == true) 
                {
                    if(document.getElementById("ip6_dhcp").checked == true) 
                    {
                        if(document.getElementById("6_dhcp_dns").checked == true)
                        {
                            var element = document.getElementById("6_dns");
                            if(element.value.trim() == "" || element.value.trim() == "::" || element.value.trim() == "::0")
                            {
                                document.getElementById("6_dns_error").innerHTML = "DNS 서버 IPv6 주소를 입력하세요.";
                                x.push(element);
                            }
                        }
                    }
                    else
                    {
                        var element = document.getElementById("6_prefix");
                        if(element.value < 1 || element.value > 128)
                        {	
                            document.getElementById("6_prefix_error").innerHTML = "서브넷 접두사 길이를 1~128사이에서 입력하세요.";
                            x.push(element);
                        }
                        
                        element = document.getElementById("6_addr");
                        if(element.value.trim() == "" || element.value.trim() == "::" || element.value.trim() == "::0")
                        {	
                            document.getElementById("6_addr_error").innerHTML = "제품 IPv6 주소를 입력하세요.";
                            x.push(element);
                        }
                    }
                }
                return x;
            }

            function onClickWlanEnable() {
                if(document.getElementById("w_enable").checked == true)
                {
                    document.getElementById("wlan_adhoc").disabled = false;
                    document.getElementById("wlan_infrastructure").disabled = false;
                    document.getElementById("wlan_soft_ap").disabled = false;
                    
                    if(document.getElementById("wlan_adhoc").checked == true)
                    {
                        document.getElementById("wlan_search_ap").disabled = true;
                        
                        document.getElementById("channel").disabled = false;
                        document.getElementById("wlan_search_channel").disabled = false;
                    
                        if(document.getElementById("ap_list").className == "List__Expand")
                            onClickCloseList('ap_list', 'ap_list_table');
                    }
                    else if(document.getElementById("wlan_infrastructure").checked == true)
                    {
                        document.getElementById("wlan_search_ap").disabled = false;
                        
                        document.getElementById("channel").disabled = true;
                        document.getElementById("wlan_search_channel").disabled = true;
                        
                        if(document.getElementById("channel_list").className == "List__Expand")
                            onClickCloseList('channel_list', 'channel_list_table');
                    }
                    else if(document.getElementById("wlan_soft_ap").checked == true)
                    {
                        document.getElementById("wlan_search_ap").disabled = true;
                        
                        document.getElementById("channel").disabled = false;
                        document.getElementById("wlan_search_channel").disabled = false;
                        
                        if(document.getElementById("ap_list").className == "List__Expand")
                            onClickCloseList('ap_list', 'ap_list_table');
                    }
                    
                    document.getElementById("ssid").disabled = false;
                    document.getElementById("shared_key").disabled = false;
                    document.getElementById("hide_shared_key").disabled = false;
                    document.getElementById("wlan_advanced").disabled = false;
                }
                else
                {
                    document.getElementById("wlan_adhoc").disabled = true;
                    document.getElementById("wlan_infrastructure").disabled = true;
                    document.getElementById("wlan_search_ap").disabled = true;
                    document.getElementById("wlan_soft_ap").disabled = true;
                    document.getElementById("channel").disabled = true;
                    document.getElementById("wlan_search_channel").disabled = true;
                    document.getElementById("ssid").disabled = true;
                    document.getElementById("shared_key").disabled = true;
                    document.getElementById("hide_shared_key").disabled = true;
                    document.getElementById("wlan_advanced").disabled = true;
                    
                    if(document.getElementById("ap_list").className == "List__Expand")
                        onClickCloseList('ap_list', 'ap_list_table');
                    if(document.getElementById("channel_list").className == "List__Expand")
                        onClickCloseList('channel_list', 'channel_list_table');
                    if(document.getElementById("advanced_option_list").className == "List__Expand")
                        onClickCloseList('advanced_option_list', 'advanced_option_list_table', false);
                }
                
                validCheckWlan();
            }

            function validCheckWlan()
            {
                var x = [];
                
                document.getElementById("ssid_error").innerHTML = "";
                
                if(document.getElementById("w_enable").checked == true)
                {    
                    var element = document.getElementById("ssid");
                    if(element.value.trim() == "")
                    {
                        document.getElementById("ssid_error").innerHTML = "SSID를 입력하세요.";
                        x.push(element);
                    }
                    else if(element.value.length > 32)
                    {
                        document.getElementById("ssid_error").innerHTML = "SSID는 최대 32자입니다.";
                        x.push(element);
                    }
                }
                
                return x;
            }

            function onClickWlanPhyMode()
            {
                if(document.getElementById("phy_auto").checked == true || document.getElementById("phy_802_11").checked == true)
                {
                    document.getElementById("preamble").disabled = true;
                    document.getElementById("slot").disabled = true;
                    document.getElementById("cts").disabled = true;
                }
                else if(document.getElementById("phy_802_11b").checked == true)
                {
                    document.getElementById("preamble").disabled = false;
                    document.getElementById("slot").disabled = true;
                    document.getElementById("cts").disabled = true;
                }
                else if(document.getElementById("phy_802_11bg").checked == true)
                {
                    document.getElementById("preamble").disabled = false;
                    document.getElementById("slot").disabled = false;
                    document.getElementById("cts").disabled = false;
                }
            }

            function onClickIpv4DhcpDns() 
            {  
                document.body.dataset.ipv4DhcpDns = document.getElementById("v4_dhcp_dns").checked == true ? 0 : 1;
                document.getElementById("4_dns").disabled = !document.getElementById("v4_dhcp_dns").checked;
                validCheckIPv4();
            }

            function onClickIpv6Type()
            {
                onClickIpv6Enable();
            }

            function onClickIpv6DhcpDns() 
            {  
                document.body.dataset.ipv6DhcpDns = document.getElementById("6_dhcp_dns").checked == true ? 0 : 1;
                document.getElementById("6_dns").disabled = !document.getElementById("6_dhcp_dns").checked;
                validCheckIPv6();
            }

            function onClickWlanType()
            {
                onClickWlanEnable();
            }

            function onClickHideWlanSharedKey() 
            {
                if(document.getElementById("hide_shared_key").checked == true)
                    document.getElementById("shared_key").type = "password";
                else
                    document.getElementById("shared_key").type = "text";
            }

            function checkIpForm(ip_addr_element)
            {
                var reg_expression = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/;    
                var constraint = new RegExp(reg_expression);
                console.log(constraint.test(ip_addr_element.value));
                return constraint.test(ip_addr_element.value);
            }
        </script>
    </body>
</html>