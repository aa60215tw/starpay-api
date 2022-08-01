<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$shared = new Shared();
$md5 = new Md5();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据
$data = json_decode($file_in,true);
$call = "抢单呼叫callback成功, " . $data. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);

if (empty($data)) {
    $fai = "抢单-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}
$pool_records = $shared->search_key($data['cusordernum']);

if($pool_records == "order_not_find"){
    $fai = "抢单-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "抢单-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$callback_sign = $data['sign'];
unset($data['sign']);
$key = $pool_records['key'];
$sign = strtoupper($md5->md5sign($key, $data));

if ($sign != $callback_sign) {
    $fai = "抢单-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "抢单-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$str = "抢单-交易成功！订单号：" . $data["cusordernum"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'success';
exit;
