<?php
error_reporting(0);
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');

$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$Babpaygw = new BabpaygwProvider();
$shared = new Shared();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据
$data = $Babpaygw->toArray($file_in);

$call = "马上富呼叫callback成功, " . $file_in. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);
if ($data["resultCode"] != "0") {
    $fai = "马上富-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'result=success';
    exit;
}

$pool_records = $shared->search_key($data["orderNo"]);
if($pool_records == "order_not_find"){
    $fai = "马上富-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'result=success';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "马上富-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'result=success';
    exit;
}

$data['key'] = $pool_records['key'];
$sign = $Babpaygw->md5sign($data);
if ($sign != $data['sign']) {
    $fai = "马上富-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'result=success';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "马上富-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'result=success';
    exit;
}

$str = "马上富-交易成功！订单号：" . $data["orderNo"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'result=success';
exit;