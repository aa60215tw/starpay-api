<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$BabHuaxing = new BabHuaxingProvider();
$shared = new Shared();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据

$clob_key='A10242D4E5F6G7H8I9J0K1M6';

$publicKey = "-----BEGIN PUBLIC KEY-----
MIGdMA0GCSqGSIb3DQEBAQUAA4GLADCBhwKBgQDd39CfsdO3nIJ3xvd5ihSFGBUWQ8mUNvCc/s2Sbxk7H6U0m/poTJLhYthfS9txZZEN90k/whp8vbkJQfjBxFnCIR9wBWOOoP5KzBA94aSXPW+UeHZGqJQmyeob6h8UpXZe4Av6Qgng61TfbOI+oyIElF8d36rgX7GXhCMkFzGRLQIBAw==
-----END PUBLIC KEY-----";

$filter_str=str_replace("001X11          00000256",'',$file_in);
$return_rsa=substr($filter_str,0,256);
$return_xml=str_replace($return_rsa,'',$filter_str);

//解码返回的rsa
$rsa_decode=$BabHuaxing->public_decrypt($return_rsa,$publicKey);

$parseXML = simplexml_load_string($return_xml,'SimpleXMLElement', LIBXML_COMPACT | LIBXML_PARSEHUGE);
$XMLPARA = $parseXML->body->XMLPARA;


$dejson=$BabHuaxing->desing3des($XMLPARA,$clob_key);

$result_json=strip_tags($dejson);
$TestStr = preg_replace('/\s(?=)/', '', $result_json);
$json_clean2=str_replace('&quot;','"',$TestStr);
$result_data=json_decode($json_clean2,true);


$call = "华兴呼叫callback成功, " . $file_in. ", JSON : ".json_encode($result_data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);
if ($result_data["status"] != "100"){
    $fai = "华兴-交易失败" . json_encode($result_data);
    $log_fail->warn($fai);
    echo callback_resp($BabHuaxing,$clob_key);
    exit;
}

$pool_records = $shared->search_key($result_data["orderNo"]);
if($pool_records == "order_not_find"){
    $fai = "华兴-没有这笔订单编号" . json_encode($result_data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo callback_resp($BabHuaxing,$clob_key);
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "华兴-水池查找失败" . json_encode($result_data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo callback_resp($BabHuaxing,$clob_key);
    exit;
}

if ($rsa_decode != strtoupper(md5($return_xml))) {
    $fai = "华兴-验签失败" . json_encode($result_data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo callback_resp($BabHuaxing,$clob_key);
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "华兴-订单更新失败" . json_encode($result_data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo callback_resp($BabHuaxing,$clob_key);
    exit;
}

$str = "华兴-交易成功！订单号：" . $result_data["orderNo"] . json_encode($result_data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo callback_resp($BabHuaxing,$clob_key);
exit;


function callback_resp($BabHuaxing,$key)
{
    $daytime=date('Ymd');
    $day_min_time=date('His');

    $channelCode='NAP024';//绑商户
    $channelFlow=$channelCode.$daytime.'001'.rand(10000,99999);
//测试私钥
    $prikey="-----BEGIN RSA PRIVATE KEY-----
MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAKG7eplU2RWR4iY9lWfAKvPxmv4LAsfyR3V7pDL588UERBoKLr4hYvnfj0Avf7tWKm7zvtvXzkCtARu2JZC890G/KG8xi/qjFMBtG/K+eWQvpugxjvaKu3zS/8IFoIyD1tXDqTIORvj7IOVYgfqLthHPlixFgWemuRyEMqxAc3PvAgMBAAECgYEAobXLB7UGucJ71LCOyoYibHeO+aQYy8M8IAPYUgAJ9Vwmm8LCqejIBf+6Q/s6RB4Ln5SnqTlGSPSyvvqI5QeMUOCIYJAxqvOvUVGkR/p7btq9QQVtcK2Hn8nSde9mShhNuH3bCDcK7oI5VgkdX6LdUPt3ZbWTiAlsJ08onPCObEECQQDcub2f6CKzyUjwHrv3TRvDkku00Zv2YIxUPDK77s8/noLoUvEv7pufbjqsaNcz7r78ULFFT3mo+A6IPx9qobNNAkEAu5Q5pVI6PQOgPU2fq7ADM76+CxtXaUlKoCJafi8nsPPn7GtDdFnn2t7Lyq6vQ+nADebu3teljoljg0/bnxyuKwJBAIv9LXvmgWPfTGgmRfaBrBMsjOFgc3ceIsIl79NrkXv673GjcR6CSacjBQll8N8aE3z5PIUF89YrhSP6TNWXOp0CQFbdTFinnHqWzES3RqLODp2Ozhj8n10NaLaBUiCvG5VRTexou8MMw1bS59LDVDyB6cNGVwXxHSTFsMSlXZHwSSMCQQCUvObJm0RqesXCbSP6SleN9ZUusf2eU+cg6AMGir9K3AI1s/oIzfr4dCm4slvengwHrHq8GthHv8dUCebh25J5
-----END RSA PRIVATE KEY-----";

    $header_xml=array(
        'channelCode'=>$channelCode,
        'channelFlow'=>$channelFlow,
        'channelDate'=>$daytime,
        'channelTime'=>$day_min_time,
        'encryptData'=>'');


    $json=json_encode(array('respCode'=>'000000','respMsg'=>'成功'));
    $sign=base64_encode($BabHuaxing->sign3des($json,$key,'RESULT_DATA'));


    $xml = "<?xml version='1.0' encoding='UTF-8'?><Document><header>";
    $xmlHeader="";
    forEach($header_xml as $k=>$v){
        $xmlHeader.='<'.$k.'>'.$v.'</'.$k.'>';
    }
    $xmlHeader.="</header>";

    $body_xml=array(
        'TRANSCODE'=>'OGW00294',
        'XMLPARA'=>$sign
    );
    $xmlbody="<body>";
    forEach($body_xml as $k=>$v){
        $xmlbody.='<'.$k.'>'.$v.'</'.$k.'>';
    }
    $xmlbody.="</body></Document>";

//完整字串
    $all_xml=$xml.$xmlHeader.$xmlbody;
    $md5_xml=strtoupper(md5($all_xml));
    $rsa=$BabHuaxing->private_encrypt($md5_xml,$prikey,$charset='bin2hex');

    return  "001X11          00000256".strtoupper($rsa).$all_xml;

}