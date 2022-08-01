<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$Md5 = new Md5();
$shared = new Shared();
$swiftpass = new SwiftpassProvider();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据
$data = $swiftpass->parseXML($file_in);

$call = "威富通呼叫callback成功". json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);
if ($data["pay_result"] != "0") {
    $fai = "威富通-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    exit("success");
}

$shared = new Shared();
$pool_records = $shared->search_key($data["out_trade_no"]);
if($pool_records == "order_not_find"){
    $fai = "威富通-没有这笔订单编号" . json_encode($data). ", 呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    exit("success");
}

if($pool_records == "pool_not_find"){
    $fai = "威富通-水池查找失败" . json_encode($data). ", 呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    exit("success");
}

$sign = $Md5->md5sign($pool_records['key'], $data);
if($sign != $data['sign']){
    $fai = "威富通-验签失败" . json_encode($data). ", 呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    exit("success");
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "威富通-订单更新失败" . json_encode($data). ", 呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    exit("success");
}

$str = "威富通-交易成功！订单号：" . $data["out_trade_no"] . json_encode($data). ", 呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
exit("success");