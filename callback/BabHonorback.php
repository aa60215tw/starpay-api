<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$BabHuaxing = new BabKailientungProvider();
$shared = new Shared();
$get_ip = $shared->get_ip();
$data = $_GET;
$partner = $_GET['partner'];//商户ID
$orderstatus = $_GET["orderstatus"]; // 支付状态
$ordernumber = $_GET["ordernumber"]; // 订单号
$paymoney = $_GET["paymoney"]; //付款金额
$sign = $_GET["sign"];	//字符加密串

$call = "荣耀支付呼叫callback成功, " . $data. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);

if ($orderstatus != "1") {
    $fai = "荣耀支付-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$pool_records = $shared->search_key($ordernumber);
if($pool_records == "order_not_find"){
    $fai = "荣耀支付-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "荣耀支付-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

//验证签名
$signSource = sprintf("partner=%s&ordernumber=%s&orderstatus=%s&paymoney=%s%s", $partner, $ordernumber, $orderstatus, $paymoney, $pool_records['key']); //连接字符串加密处理
if ($sign != md5($signSource))//签名正确
{
    $fai = "荣耀支付-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);

if (!$orderList) {
    $fai = "荣耀支付-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$str = "荣耀支付-交易成功！订单号：" . $ordernumber . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'ok';
exit;