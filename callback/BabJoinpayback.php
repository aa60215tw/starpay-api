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

$key_array=array(
    '888103700002521'=>'811edf57252c4423b17c91a11f8ea715',
    '888103700002520'=>'87d8ccc5c81a4554b2948f064a3fa03c',
    '888105000005221'=>'09b6b50c7489411aa8de08d977b66a65',
    '888105200000607' => 'c82970672494497889fd8087bb5c31dc',
    '888105200000368' => 'b8fa00bf835742f5aec61bc476154441',
);

$call = "汇聚支付呼叫callback成功, " . $data. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);

if ($data['r6_Status'] != "100") {
    $fai = "汇聚支付-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$pool_records = $shared->search_key($data['r2_OrderNo']);

if($pool_records == "order_not_find"){
    $fai = "汇聚支付-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "汇聚支付-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$returnSign=$data["hmac"];
$data['ra_PayTime'] = urldecode($data['ra_PayTime']);
$data['rb_DealTime'] = urldecode($data['rb_DealTime']);
$data['r5_Mp'] = urldecode($data['r5_Mp']);

$use_key=$key_array[$data['r1_MerchantNo']];

$newSign=md5sign($data, $use_key);


if ($returnSign != $newSign) {
    $fai = "汇聚支付-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "汇聚支付-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$str = "汇聚支付-交易成功！订单号：" . $data["r2_OrderNo"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'success';
exit;

function md5sign($data,$signKey)
{
    ksort($data);
    $md5str = "";
    foreach ($data as $k=>$v){
        if("" != $v && "hmac" != $k){
            $md5str.=$v;
        }
    }
    $md5str = $md5str.$signKey;
    $sign=md5($md5str);
    return $sign;
}

