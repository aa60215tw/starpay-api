<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$BabGeq = new BabGeqProvider();
$shared = new Shared();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据
$data = $BabGeq->toArray(urldecode($file_in));

$call = "钜石呼叫callback成功, " . $file_in. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);
if ($data["trade_state"] != "0") {
    $fai = "钜石-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

$pool_records = $shared->search_key($data["out_trade_no"]);
if($pool_records == "order_not_find"){
    $fai = "钜石-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "钜石-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

$callback_sign = $data['sign'];
unset($data['sign_type']);
$sign = $BabGeq->md5sign($data,$pool_records['key']);
if ($sign != $data['sign']) {
    $fai = "钜石-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "钜石-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

$str = "钜石-交易成功！订单号：" . $data["out_trade_no"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'success';
exit;

