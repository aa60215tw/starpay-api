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
$call = "新生支付呼叫callback成功, " . $data. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);

$key_array = [
    '201901082000001' => '3013b44d5170ef38jwrc42a9c6a989jf',
    '201902272000002' => '87k8904d5170ef3u98rc42a9c6a989jf',
    '201903072000003' => 'eo92i04d5170ef3u3u7h92a9c6a98j5k',
    '201903192000004' => 'eo92i04k5h80ef39j55h92k09ga98j5k',
    '201903192000005' => '831ky7517a3d64df5f0ed03ty52d3af0',
    '201904042000006' => 'k7r8y7517a3d6i98hf0ed03ty529ru70',
    '201904122000007' => '79h8y7517a3d6iu9u5t7d03ty529i89k',
    '201904192000009' => '9dk89je45u03oi97k0t7d03ty529i89k',
    '201905012000010' => 'h8jo9je45u03oi9u03i7d03ty529i89k',
    '201905052000011' => 'r9h89je87a5o7h97k0t7d03ty529i89k',
];

if (empty($data)) {
    $fai = "新生支付-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}
$pool_records = $shared->search_key($data['pay_order_id']);

if($pool_records == "order_not_find"){
    $fai = "新生支付-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "新生支付-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$callback_sign = $data['pay_md5_sign'];
unset($data['pay_md5_sign']);

$key = $pool_records['key'];
if (isset($key_array[$data['pay_memberid']])) {
    $key = $key_array[$data['pay_memberid']];
}

$sign = strtoupper($md5->md5sign($key, $data));

if ($sign != $callback_sign) {
    $fai = "新生支付-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "新生支付-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$str = "新生支付-交易成功！订单号：" . $data["pay_order_id"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'success';
exit;
