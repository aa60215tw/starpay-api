<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');

$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$hnapay = new HnapayProvider();
$shared = new Shared();
$Rsa = new Rsa();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据
$t_mid = $_GET['tmid'];
$data = $hnapay->http_build_url($file_in);
$call = "新生呼叫callback成功, " . $file_in. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip.'GET'.$t_mid.'GET1'.json_encode($_GET);
$log_call->warn($call);
if ($data["respCode"] != "0000") {
    $fai = "新生-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$pool_records = $shared->search_key($data["merOrderNum"]);
if($pool_records == "order_not_find"){
    $fai = "新生-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "新生-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$jsapi = array(
    "tranCode" => $data['tranCode'],
    "version" => $data['version'],
    "merId" => $data['merId'],
    "merOrderNum" => $data['merOrderNum'],
    "tranAmt" => $data['tranAmt'],
    "submitTime"   => $data['submitTime'],
    "hnapayOrderId"  =>  $data['hnapayOrderId'],
    "tranFinishTime" => $data['tranFinishTime'],
    "respCode" => $data['respCode'],
    "charset" => $data['charset'],
    "signType" => $data['signType']
);

$rsastr = $hnapay->getpayData($jsapi);
$rsasign = $Rsa->rsa_verify($rsastr,$pool_records,$data['signMsg'],$OPENSSL=OPENSSL_ALGO_SHA1,$charset='bin2hex');
if(!$rsasign){
    $fai = "新生-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$payment_time = date("Y-m-d H:i:s", strtotime($data['tranFinishTime']));
$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records, $payment_time);
if (!$orderList) {
    $fai = "新生-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$str = "新生-交易成功！订单号：" . $data["merOrderNum"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 200;
exit;