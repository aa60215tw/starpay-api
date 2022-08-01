<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$shared = new Shared();
$BabMwapayProvider = new BabMwapayProvider();
$MD5 = new Md5();
$get_ip = $shared->get_ip();
$file_in = $BabMwapayProvider->array_iconv($_GET); //接收post数据
$xmlString = '<retcode>00</retcode>';


$call = "天付宝网银呼叫callback成功, " . $file_in. ", JSON : ".json_encode($file_in). ", 呼叫IP：". $get_ip;
$log_call->warn($call);
if ($file_in["result"] != "1") {
    $fai = "天付宝网银-交易失败" . json_encode($file_in);
    $log_fail->warn($fai);
    echo $xmlString;
    exit;
}

$pool_records = $shared->search_key($file_in["spbillno"]);
if($pool_records == "order_not_find"){
    $fai = "天付宝网银-没有这笔订单编号" . json_encode($file_in). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo $xmlString;
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "天付宝网银-水池查找失败" . json_encode($file_in). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo $xmlString;
    exit;
}

$file_in_sign = $file_in['sign'];
unset($file_in['retcode'],$file_in['retmsg']);
$sign = $MD5->md5sign($pool_records['key'],$file_in,'on','off');

if ($sign != $file_in_sign) {
    $fai = "天付宝网银-验签失败" . json_encode($file_in). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo $xmlString;
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "天付宝网银-订单更新失败" . json_encode($file_in). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo $xmlString;
    exit;
}

$str = "天付宝网银-交易成功！订单号：" . $file_in["spbillno"] . json_encode($file_in). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo $xmlString;
exit;