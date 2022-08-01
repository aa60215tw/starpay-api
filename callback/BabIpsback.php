<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(0);

$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success= new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$BabIps = new BabIpsProvider();
$shared = new Shared();
$get_ip = $shared->get_ip();
$file_in = file_get_contents("php://input"); //接收post数据
parse_str($file_in, $data);
$data = $data['paymentResult'];

$data_array = $BabIps->parseXML($data);
$data_array = $data_array['GateWayRsp'];
$data_array = "<OrderQueryRsp>$data_array</OrderQueryRsp>";
$data_array = $BabIps->parseXML($data_array);
$output_head_xml = $data_array['head'];
$output_head_xml = "<head>$output_head_xml</head>";
$output_head = $BabIps->parseXML($output_head_xml);
$output_body_xml = $data_array['body'];
$output_body_xml = "<body>$output_body_xml</body>";
$output_body = $BabIps->parseXML($output_body_xml);

$call = "环迅呼叫callback成功, " . $file_in. ", JSON : ".json_encode($data). ", 呼叫IP：". $get_ip;
$log_call->warn($call);
if ($output_body["Status"] != "Y") {
    $fai = "环迅-交易失败" . json_encode($data);
    $log_fail->warn($fai);
    echo 'RetType=1';
    exit;
}

$pool_records = $shared->search_key($output_body["MerBillNo"]);
if($pool_records == "order_not_find"){
    $fai = "环迅-没有这笔订单编号" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'RetType=1';
    exit;
}

if($pool_records == "pool_not_find"){
    $fai = "环迅-水池查找失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'RetType=1';
    exit;
}

$Signature = md5($output_body_xml.$pool_records['t_account'].$pool_records['key']);
if ($Signature != $output_head['Signature'])
{
    $fai = "环迅-验签失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'RetType=1';
    exit;
}

$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "环迅-订单更新失败" . json_encode($data). "呼叫IP：". $get_ip;
    $log_fail->warn($fai);
    echo 'RetType=1';
    exit;
}

$str = "环迅-交易成功！订单号：" . $data["orderNo"] . json_encode($data). "呼叫IP：". $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'RetType=1';
exit;