<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$BabSqb = new BabShouqianbaProvider();
$shared = new Shared();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据
$data = json_decode($file_in, true);

$call = "收钱吧支付呼叫callback成功, " . $file_in. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);
if ($data['status'] != "SUCCESS") {
    $fai = "收钱吧支付-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$pool_records = $shared->search_key($data['client_sn']);
if($pool_records == "order_not_find"){
    $fai = "收钱吧支付-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "收钱吧支付-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$order_parm = array(
    "t_mid"=>$pool_records["t_mid"],
    "upstream_order_number" => $pool_records["upstream_order_number"],
    "key"=>$pool_records["key"],
    "query"=>"https://api.shouqianba.com/upay/v2/query",
);

$query_order = $BabSqb->getOrder($order_parm);
if($query_order != 0){
    $fai = "收钱吧支付-查單失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "收钱吧支付-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$str = "收钱吧支付-交易成功！订单号：" . $data["client_sn"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'SUCCESS';
exit;
