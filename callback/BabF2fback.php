<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'controllers/SelfApiController.php');
error_reporting(-1);

$pool_judgment = 'user_id';
$log_call = new LoggerHelper('pay_call', PAY_LOG_CALL);
$log_fail = new LoggerHelper('pay_fail', PAY_LOG_FAIL);
$log_success = new LoggerHelper('pay_success', PAY_LOG_SUCCESS);
$shared = new Shared();
$get_ip = $shared->get_ip();
$data = '{"gmt_create":"2018-12-03 15:58:38","charset":"UTF-8","seller_email":"djkk43@163.com","subject":"ä¸šåŠ¡è®¢å•å·:20181203155833788652b00056","sign":"A4S4rLOfl+kIZ44MQsH7eEN7GEU8EnhVQs9kqz63d4Tz8c4x2FSwimkhwDcr5L4Xc4IuiM1g64hT9dAiLnDE+b/czf2cN0uhGDdXu9EgrUWYg32taCS5tjRB2vvxx85TgzU/w2fFeGcrEray77PUkE/PHiNsW+RrQSp0YnrMzkg+ADnl5LRuv/2wTe33IdiEVhakLXXlneB8gEAyZjqc0BBzJdWBFn+wRFTPn2ATQvzJn56Jo2sgsd4yiHLzlZAA0DKAeq/bzPb2zb9ac0vlSwbKgWixRpRPZuiBwTXmC+bDiHGS0o7v31Chv1W0JpCaiJxf+SHYUcLeVD4V01qncw==","buyer_id":"2088332083669130","invoice_amount":"0.10","notify_id":"2018120300222155854069135459830708","fund_bill_list":"[{\"amount\":\"0.10\",\"fundChannel\":\"ALIPAYACCOUNT\"}]","notify_type":"trade_status_sync","trade_status":"TRADE_SUCCESS","receipt_amount":"0.10","buyer_pay_amount":"0.10","app_id":"2018112362303497","sign_type":"RSA2","seller_id":"2088331547500606","gmt_payment":"2018-12-03 15:58:54","notify_time":"2018-12-03 15:58:54","version":"1.0","out_trade_no":"201812031558337702eeqbtkme","total_amount":"0.10","trade_no":"2018120322001469135437642789","auth_app_id":"2018112362303497","buyer_logon_id":"886-****88115","point_amount":"0.00"}';
$data = json_decode($data , true);
$call = "汇聚支付呼叫callback成功," . json_encode($data,320) . ", 呼叫IP：" . $get_ip;
$log_call->warn($call);
var_dump($data);
if ($data['trade_status'] != "TRADE_SUCCESS") {
    $fai = "當面付-交易失败" . json_encode($data,320);
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$pool_records = $shared->search_key($data['out_trade_no']);

if ($pool_records == "order_not_find") {
    $fai = "當面付-没有这笔订单编号" . json_encode($data,320) . "呼叫IP：" . $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

if ($pool_records == "pool_not_find") {
    $fai = "當面付-水池查找失败" . json_encode($data,320) . "呼叫IP：" . $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}
$controller = new SelfApiController();
$orderList = $controller->completePay($pool_records);
if (!$orderList) {
    $fai = "當面付-订单更新失败" . json_encode($data,320) . "呼叫IP：" . $get_ip;
    $log_fail->warn($fai);
    echo 'error';
    exit;
}

$str = "當面付-交易成功！订单号：" . $data["out_trade_no"] . "||" . json_encode($data,320) . "呼叫IP：" . $get_ip;
$log_success->warn($str);
$shared->call_curl($orderList);
echo 'success';
exit;


