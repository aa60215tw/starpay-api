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
$file_in = file_get_contents("php://input"); //接收post数据
$data = json_decode($file_in,true);

$call = "快捷通支付呼叫callback成功, " . $data. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);

if ($data['trade_status'] != "TRADE_SUCCESS" && $data['trade_status'] != "TRADE_FINISHED") {
    $fai = "快捷通支付-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$pool_records = $shared->search_key($data['outer_trade_no']);

if($pool_records == "order_not_find"){
    $fai = "快捷通支付-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "快捷通支付-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$verify = verify($data,$pool_records['key1']);
if (!$verify) {
    $fai = "快捷通支付-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "快捷通支付-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$str = "快捷通支付-交易成功！订单号：" . $data["outer_trade_no"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'success';
exit;


function verify($data,$kjtPublicKey) {
    foreach ($data as $k => $v) {
        if (strcasecmp($k, 'sign') == 0
            || strcasecmp($k, 'sign_type') == 0 || $v == '' || $v == null) {
            continue;
        }
        $params[$k] = $v;
    }
    if($params) {
        $result = '';
        ksort($params);
        foreach ($params as $k => $v) {
            $result .= $k . '=' . $v . '&';
        }
        $result = substr($result, 0, -1);
    }
    $sign_result = openssl_verify($result, base64_decode($data['sign']), $kjtPublicKey);
    return $sign_result;
}
