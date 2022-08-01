<?php

require_once 'HJAlipayH5.php';

$HJAlipayH5 = new HJAlipayH5();
$pay_result = $HJAlipayH5->pay();

// $HJAlipayH5 = new HJAlipayH5('60504488652b00056');
// $pay_result = '{"message_code":"000","message":"成功","partner":"652b00056","paymoney":"0.2","ordernumber":"60504488652b00056","sysnumber":"20181224143457362652b00056","url":"alipays://platformapi/startapp?appId=20000067&url=http://127.0.0.1:8081/zong/zhuan.php?r=eyJ0aWQiOiIyMzYyT01XIiwicG9vbF9qdWRnbWVudCI6IiIsIndhaXRfdGltZSI6MTI5MCwiaXBfanVkZ21lbnQiOnRydWUsInN0cmF0ZWd5X3N0YXJ0IjowfQ==","sign":"1A4AFF5BD20BBF13576DF1FF44DED73C"}';

print_r($pay_result);
$pay_data = json_decode($pay_result, true);
print_r($pay_data);
if (empty($pay_data) || $pay_data['message_code'] != '000') {
    $HJAlipayH5->fail_print('送单失败');
}
$HJAlipayH5->verify_paydata($pay_data);

$initData = $HJAlipayH5->db_get_by_ordernum();
if (empty($initData['id'])) {
    $HJAlipayH5->fail_print('资料库新增失败');
}

$id = $initData['id'];
$upstream_order_number = $initData['upstream_order_number'];
print_r(PHP_EOL . 'id = ' . $id);
print_r(PHP_EOL . 'upstream_order_number = ' . $upstream_order_number);

$zhuan_url = $HJAlipayH5->fetchZhuanUrl($pay_data['url']);

$zhuan_url =$HJAlipayH5->generateZhuanUrl($zhuan_url);
print_r(PHP_EOL . '中转url = ' . $zhuan_url);

$r = $HJAlipayH5->fetchR($zhuan_url);
print_r(PHP_EOL . 'r = ' . $r);

$result = $HJAlipayH5->emulateZhuan($zhuan_url);
print_r(PHP_EOL . '结果 ' . $result);

$url = json_decode($result, true);
if (empty($url['msg'])) {
    $HJAlipayH5->fail_print('url为空');
}
$url = $url['msg'];
print_r(PHP_EOL . '支付 url = ' . $url);


$data = $HJAlipayH5->db_get_by_id($id);

if ($data['id'] != $id) {
    $HJAlipayH5->fail_print('id异常');
}
// 检查上游订单号 $upstream_order_number
if ($data['upstream_order_number'] != $upstream_order_number) {
    $HJAlipayH5->fail_print('上游订单号异常');
}
// 检查qr_url $url
if ($data['qr_url'] != $url) {
    $HJAlipayH5->fail_print('url 异常');
}
// 检查payment_status 4
if ($data['payment_status'] != '4') {
    $HJAlipayH5->fail_print('payment_status 异常');
}

print_r(PHP_EOL);