<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);
header('Content-Type: application/json');
$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$shared = new Shared();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据
$data = json_decode($file_in,true);

$call = "钜石网银呼叫callback成功, " . $file_in. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);
if ($data["status"] != "0") {
    $fai = "钜石网银-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo json_encode(array('msg'=>'success'),320);
    exit;
}

$pool_records = $shared->search_key($data["order_no"]);
if($pool_records == "order_not_find"){
    $fai = "钜石网银-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo json_encode(array('msg'=>'success'),320);
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "钜石网银-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo json_encode(array('msg'=>'success'),320);
    exit;
}

$data_sign = $data['sign'];
unset($data['sign']);
ksort($data);
$md5str = http_build_query($data);
$md5str = urldecode($md5str);
$sign = md5($md5str.md5($pool_records['key']));

if ($sign != $data_sign) {
    $fai = "钜石网银-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo json_encode(array('msg'=>'success'),320);
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "钜石网银-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo json_encode(array('msg'=>'success'),320);
    exit;
}

$str = "钜石网银-交易成功！订单号：" . $data["out_trade_no"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo json_encode(array('msg'=>'success'),320);
exit;

