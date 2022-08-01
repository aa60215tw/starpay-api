<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$shared = new Shared();
$get_ip = $shared->get_ip();
$data = $_GET;

$call = "CLT呼叫callback成功, " . $data. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);

$pool_records = $shared->search_key($data['sn']);
if($pool_records == "order_not_find"){
    $fai = "CLT-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "CLT-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

//验证签名
$callback_sign = $data['sign'];
unset($data['sign']);
unset($data['money']);
ksort($data);
$signstr = '';
foreach($data as $k => $v) {
    if($v != '') {
        $signstr .= $k . "=" . $v . "&";
    }
}
$signstr = substr($signstr,0,-1);
$signstr .= $pool_records['key'];
$verify_sign=md5($signstr);
if ($callback_sign != $verify_sign) {
    $fai = "CLT-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);

if (!$orderList) {
    $fai = "CLT-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

$str = "CLT-交易成功！订单号：" . $data["sn"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'success';
exit;