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
$call = "转账银行卡呼叫callback成功, JSON : ". $file_in . "呼叫IP：". $get_ip;
$log_call->warn($call);

if (empty($data)) {
    $fai = "转账银行卡-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}
$pool_records = $shared->search_key($data['order_id']);

if($pool_records == "order_not_find"){
    $fai = "转账银行卡-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "转账银行卡-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$callback_sign = $data['sign'];
unset($data['sign']);
$sign = $md5->md5sign($pool_records['key'], $data);

if ($sign != $callback_sign) {
    $fai = "转账银行卡-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "转账银行卡-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$str = "转账银行卡-交易成功！订单号：" . $data["pay_order_id"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'success';
exit;
