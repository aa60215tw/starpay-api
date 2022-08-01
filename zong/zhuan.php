<?php
require_once('../configs/sys.config.inc.php');
// require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');

$headers = array();
foreach ($_SERVER as $key => $value) {
    if ('HTTP_' == substr($key, 0, 5)) {
        $headers[str_replace('_', '-', substr($key, 5))] = $value;
    }
}
$aliappip = $headers["X-REAL-IP"];
$ip = get_ip();
$asyn = $_GET['asyn'];

if ($asyn == 1) {
    $temp = explode('?', $_GET['r']);
    $r = $temp[0];
    $other = [];
    if (isset($temp[1])) {
        parse_str($temp[1], $other);
    }
    if (empty($r)) {
        exit;
    }
    $r = json_decode(base64_decode($r), true);

    $tid = substr($r['tid'], 0, -3);//订单ID
    if (!$tid)
        exit;
    $token = substr($r['tid'], -3);
    $ntoken = shorturl($tid);
    $retoken = substr($ntoken, -3);

    $alipay_app_id = '';
    $alipay_user_id = $_GET['alipay_user_id'];
    if (!empty($_GET['alipay_app_id'])) {
        $alipay_app_id = $_GET['alipay_app_id'];
    } elseif (!empty($_GET['app_id'])) {
        $alipay_app_id = $_GET['app_id'];
    } elseif (!empty($_GET['openid'])) {
        $alipay_user_id = $_GET['openid'];
    }

    if ($token == $retoken) {
        $data = $_POST["data"];
        // $zhuan_log = new LoggerHelper('zhuan_log', CLIENT_REQUEST_LOG);
        $output = curl(
            ZONG_API_URL,
            [
                'id' => $r['tid'],
                'ip' => $ip,
                'r' => $_GET['r'],
                'auth_code' => $_GET['auth_code'],
                'alipay_user_id' => $alipay_user_id,
                'alipay_app_id' => $alipay_app_id,
                'pool_judgment' => $r['pool_judgment'],
                'wait_time' => $r['wait_time'],
                'ip_judgment' => $r['ip_judgment'],
                'strategy_start' => $r['strategy_start'],
                'rank' => $r['rank'],
                'district' => $data['district'],
                'districtAdcode' => $data['districtAdcode']
            ]
        );
        // $zhuan_log->warn(sprintf('id: [%s] %s', $r['tid'], $output));
        $output_array = json_decode($output, true);
        if (!$output_array || $output_array['message_code'] != "000" || empty($output_array['url'])) {
            $return = [
                "code" => "1",
                "msg" => "订单异常，请点击右上角刷新一次，或尝试重新送单"
            ];
            if ($output_array['message_code'] == "997") {
                $return['msg'] = '您的支付宝账户已被风控，请跟换支付宝在进行支付';
            }
            exit(json_encode($return, 320));
        }

        // 检查 http 开头
        $is_http = (substr($output_array['url'], 0, 4) === "http");
        exit(json_encode([
            "code" => "2",
            "msg" => $output_array['url'],
            "type" => $output_array['swift'],
            "http" => $is_http,
        ], 320));

        /*
        $qrcodeurl = urlencode($output_array['url']);
        $qrcodeurl = "https://ds.alipay.com/?from=mobilecodec&scheme=alipays%3A%2F%2Fplatformapi%2Fstartapp%3FsaId%3D10000007%26qrcode%3D" . $qrcodeurl;
        exit(json_encode(array("code" => "2","msg" => $qrcodeurl),320));*/
    }
    exit();
} elseif ($asyn == 2) {
    $r = $_GET['r'];

    if (empty($r))
        exit;

    $r = json_decode(base64_decode($r), true);

    $tid = substr($r['tid'], 0, -3);//订单ID
    if (!$tid)
        exit;
    $token = substr($r['tid'], -3);
    $ntoken = shorturl($tid);
    $retoken = substr($ntoken, -3);

    if ($token == $retoken) {
        if (!empty($_POST['resultCode'])) {
            curl(URL . 'pay/alipay_jsapi_result', ['id' => $r['tid'], 'resultCode' => $_POST['resultCode']]);
        }

        exit(json_encode(array("code" => "2", "msg" => ''), 320));
    }
    exit();
}

function get_ip()
{
    //判断服务器是否允许$_SERVER
    if (isset($_SERVER)) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $realip = explode(',', $realip)[0];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $realip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $realip = $_SERVER['REMOTE_ADDR'];
        }
    } else {
        //不允许就使用getenv获取
        if (getenv("HTTP_X_FORWARDED_FOR")) {
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $realip = getenv("HTTP_CLIENT_IP");
        } else {
            $realip = getenv("REMOTE_ADDR");
        }
    }

    return $realip;
}

function code62($x)
{
    $show = '';
    while ($x > 0) {
        $s = $x % 62;
        if ($s > 35) {
            $s = chr($s + 61);
        } elseif ($s > 9 && $s <= 35) {
            $s = chr($s + 55);
        }
        $show .= $s;
        $x = floor($x / 62);
    }
    return $show;
}

function shorturl($url)
{
    $url = crc32($url);
    $result = sprintf("%u", $url);
    return code62($result);
}

function curl($url, $data)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

?>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<style>
    #loading3 {
        position: relative;
        width: 50px;
        height: 50px;
    }

    .demo3 {
        width: 4px;
        height: 4px;
        border-radius: 2px;
        background: #68b2ce;
        position: absolute;
        animation: demo3 linear 0.8s infinite;
        -webkit-animation: demo3 linear 0.8s infinite;
    }

    .demo3:nth-child(1) {
        left: 24px;
        top: 2px;
        animation-delay: 0s;
    }

    .demo3:nth-child(2) {
        left: 40px;
        top: 8px;
        animation-delay: 0.1s;
    }

    .demo3:nth-child(3) {
        left: 47px;
        top: 24px;
        animation-delay: 0.1s;
    }

    .demo3:nth-child(4) {
        left: 40px;
        top: 40px;
        animation-delay: 0.2s;
    }

    .demo3:nth-child(5) {
        left: 24px;
        top: 47px;
        animation-delay: 0.4s;
    }

    .demo3:nth-child(6) {
        left: 8px;
        top: 40px;
        animation-delay: 0.5s;
    }

    .demo3:nth-child(7) {
        left: 2px;
        top: 24px;
        animation-delay: 0.6s;
    }

    .demo3:nth-child(8) {
        left: 8px;
        top: 8px;
        animation-delay: 0.7s;
    }

    @keyframes demo3 {
        0%, 40%, 100% {
            transform: scale(1);
        }
        20% {
            transform: scale(3);
        }
    }

    @-webkit-keyframes demo3 {
        0%, 40%, 100% {
            transform: scale(1);
        }
        20% {
            transform: scale(3);
        }
    }

    .main {
        text-align: center; /*让div内部文字居中*/
        background-color: #fff;
        border-radius: 20px;
        width: 50px;
        height: 50px;
        margin: auto;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        font-size: 18px;
    }

    @-webkit-keyframes e {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg)
        }

        to {
            -webkit-transform: rotate(1turn);
            transform: rotate(1turn)
        }
    }

    @keyframes e {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg)
        }

        to {
            -webkit-transform: rotate(1turn);
            transform: rotate(1turn)
        }
    }

    .loading {
        width: 20px;
        height: 20px;
        display: inline-block;
        vertical-align: middle;
        -webkit-animation: e 1s steps(12) infinite;
        animation: e 1s steps(12) infinite;
        background: transparent url("data:image/svg+xml;charset=utf8, %3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 100 100'%3E%3Cpath fill='none' d='M0 0h100v100H0z'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23E9E9E9' rx='5' ry='5' transform='translate(0 -30)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23989697' rx='5' ry='5' transform='rotate(30 105.98 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%239B999A' rx='5' ry='5' transform='rotate(60 75.98 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23A3A1A2' rx='5' ry='5' transform='rotate(90 65 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23ABA9AA' rx='5' ry='5' transform='rotate(120 58.66 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23B2B2B2' rx='5' ry='5' transform='rotate(150 54.02 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23BAB8B9' rx='5' ry='5' transform='rotate(180 50 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23C2C0C1' rx='5' ry='5' transform='rotate(-150 45.98 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23CBCBCB' rx='5' ry='5' transform='rotate(-120 41.34 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23D2D2D2' rx='5' ry='5' transform='rotate(-90 35 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23DADADA' rx='5' ry='5' transform='rotate(-60 24.02 65)'/%3E%3Crect width='7' height='20' x='46.5' y='40' fill='%23E2E2E2' rx='5' ry='5' transform='rotate(-30 -5.98 65)'/%3E%3C/svg%3E") no-repeat;
        background-size: 100%;
        background-image: url("data:image/svg+xml; charset=utf8,%3Csvg xmlns= 'http://www.w3.org/2000/svg' width= '120' height= '120' viewBox= '0 0 100 100' %3E%3Cpath fill= 'none' d= 'M0 0h100v100H0z' /%3E%3Crect xmlns= 'http://www.w3.org/2000/svg' width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.56)' rx= '5' ry= '5' transform= 'translate(0 -30)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.5)' rx= '5' ry= '5' transform= 'rotate(30 105.98 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.43)' rx= '5' ry= '5' transform= 'rotate(60 75.98 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.38)' rx= '5' ry= '5' transform= 'rotate(90 65 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.32)' rx= '5' ry= '5' transform= 'rotate(120 58.66 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.28)' rx= '5' ry= '5' transform= 'rotate(150 54.02 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.25)' rx= '5' ry= '5' transform= 'rotate(180 50 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.2)' rx= '5' ry= '5' transform= 'rotate(-150 45.98 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.17)' rx= '5' ry= '5' transform= 'rotate(-120 41.34 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.14)' rx= '5' ry= '5' transform= 'rotate(-90 35 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.1)' rx= '5' ry= '5' transform= 'rotate(-60 24.02 65)' /%3E%3Crect width= '7' height= '20' x= '46.5' y= '40' fill= 'rgba(255,255,255,.03)' rx= '5' ry= '5' transform= 'rotate(-30 -5.98 65)' /%3E%3C/svg%3E ")
    }
</style>
<body ontouchstart>
<div id="result" class="main" style="width:200px;z-index:9999;display:none;"></div>
<div id="loading" class="main" style="width:200px;z-index:9000;">
    <div class="loading">
    </div>
    <div id="load">订单加载中...</div>
</div>
<div class="main" style="display:none;">
    <div id="loading3">
        <div class="demo3"></div>
        <div class="demo3"></div>
        <div class="demo3"></div>
        <div class="demo3"></div>
        <div class="demo3"></div>
        <div class="demo3"></div>
        <div class="demo3"></div>
        <div class="demo3"></div>
    </div>
</div>
</body>
<script src="../public/js/jquery-3.3.1.min.js"></script>
<script src="https://gw.alipayobjects.com/as/g/h5-lib/alipayjsapi/3.1.1/alipayjsapi.inc.min.js"></script>
<script>

    $(function () {
        alert("请开启定位服务，若未开启可能无法支付");
        function ready(callback) {
            // 如果jsbridge已经注入则直接调用
            if (window.AlipayJSBridge) {
                callback && callback();
            } else {
                // 如果没有注入则监听注入的事件
                document.addEventListener('AlipayJSBridgeReady', callback, false);
            }
        }

        ready(function () {
            AlipayJSBridge.call('getCurrentLocation', {requestType: 2, bizType: 'didi'}, function (result) {
                if (result.error) {
                    getOrder()
                } else {
                    getOrder({'district': result.district, 'districtAdcode': result.districtAdcode})
                }
            });
        });

        function getOrder(data) {
            if ('undefined' === typeof(data)) {
                data = null;
            }
            $.post(window.location.href + "&asyn=1", {'data': data}, function (result) {
                $("#loading").hide();
                $("#result").show();
                if (result.code == "1") {
                    $("#result").html(result.msg);
                }
                else if (result.code == "2") {
                    if (result.type == 'ALIPAY_APP' && !(result.http)) {
                        ap.tradePay({
                            tradeNO: result.msg
                        }, function (res) {
                            // ap.alert(res.resultCode);
                            // ap.popWindow();

                            $.post(window.location.href + "&asyn=2", {resultCode: res.resultCode}, function (result) {
                            }, "json")
                                .always(function () {
                                    ap.popWindow();
                                });
                        });
                    } else {
                        location.href = result.msg;
                    }
                }
                else
                    $("#result").html("网络错误，请点击右上角刷新一次");
            }, "json");

            //执行showTime()
            showTime();
        }
    });

    var t = 25;

    function showTime() {
        t -= 1;
        if (t > 0 && t < 20) {
            document.getElementById('load').innerHTML = "订单加载中..." + t + "秒";
        }
        if (t == 0) {
            $("#loading").html("网络错误，请点击右上角刷新一次");
        }
        if (t >= 0) {
            //每秒执行一次,showTime()
            setTimeout("showTime()", 1000);
        }

    }

</script>