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
$file_in = file_get_contents("php://input"); //接收post数据
parse_str($file_in, $data);

$call = "开联通呼叫callback成功, " . $file_in. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);

if ($data["payResult"] != "1") {
    $fai = "开联通-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$pool_records = $shared->search_key($data['orderNo']);
if($pool_records == "order_not_find"){
    $fai = "开联通-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "开联通-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

//验证签名
$return_sign = $data['signMsg'];
$signstr='';
$param = array(
    'merchantId' => $data['merchantId'],
    'version' => $data['version'],
    'language' => $data['language'],
    'signType' => $data['signType'],
    'payType' => $data['payType'],
    'issuerId' => $data['issuerId'],
    'mchtOrderId' => $data['mchtOrderId'],
    'orderNo' => $data['orderNo'],
    'orderDatetime' => $data['orderDatetime'],
    'orderAmount' =>  $data['orderAmount'],
    'payDatetime' => $data['payDatetime'],
    'ext1' => $data['ext1'],
    'ext2' => $data['ext2'],
    'payResult' => $data['payResult'],
);
foreach($param as $k => $v) {
    if($v != '') {
        $signstr .= $k . "=" . $v . "&";
    }
}
$signstr .= 'key='. $pool_records['key'];
$data_sign=strtoupper(md5($signstr));

if ($data_sign != $return_sign) {
    $fai = "开联通-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}
//验证签名end

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);

if (!$orderList) {
    $fai = "开联通-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'success';
    exit;
}

$str = "开联通-交易成功！订单号：" . $data["orderNo"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'success';
exit;