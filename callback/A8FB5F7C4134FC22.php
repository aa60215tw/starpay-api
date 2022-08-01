<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$shared = new Shared();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据
$data = json_decode($file_in, true);
$call = "LQ复联支付呼叫callback成功, " . $file_in. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);

if ($data['orderStatus'] != '2') {
    $fai = "LQ复联支付-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'ERROR';
    exit;
}
$pool_records = $shared->search_key($data['outOid']);

if($pool_records == "order_not_find"){
    $fai = "LQ复联支付-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'ERROR';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "LQ复联支付-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'ERROR';
    exit;
}

$callback_sign = $data['sign'];
unset($data['sign'], $data['notifyType']);
$key = $pool_records['key'];
ksort($data);
foreach ($data as $k => $v) {
    if ("" != $v && "sign" != $k) {
        $signPars .= $k . "=" . $v . "&";
    }
}
$signPars = $signPars.'key='.$key;
$sign = strtoupper(md5($signPars));

if ($sign != $callback_sign) {
    $fai = "LQ复联支付-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'ERROR';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);

if (!$orderList) {
    $fai = "LQ复联支付-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'ERROR';
    exit;
}

$str = "LQ复联支付-交易成功！订单号：" . $data["outOid"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'SUCCESS';
exit;

