<?php

require_once 'HJAlipayH5.php';

$upstream_order_number = '201812261649553692eeqbtkme04';
$db_upstream_order_number = $upstream_order_number;
if (is_numeric(substr($db_upstream_order_number, -2))) {
    $db_upstream_order_number = substr($db_upstream_order_number, 0, -2);
}

$HJAlipayH5 = new HJAlipayH5();
$data = $HJAlipayH5->db_get_by_upstream($db_upstream_order_number);
$id = $data['id'];
$callbackData = [
    'r1_MerchantNo' => $data['t_account'],
    'r2_OrderNo' => $upstream_order_number,
    'r3_Amount' => $data['pay_amount'],
    'r4_Cur' => '1',
    'r5_Mp' => $data['t_mid'],
    'r6_Status' => '100',
    'r7_TrxNo' => '100218102225618358',
    'r8_BankOrderNo' => '100218102225618358',
    'r9_BankTrxNo' => '101510285310201810226686014434',
    'ra_PayTime' => date("Y-m-d H:i:s"),
    'rb_DealTime' => date("Y-m-d H:i:s"),
    'rc_BankCode' => "ALIPAY_NATIVE",
];

$pool_key = $HJAlipayH5->db_pool_by_taccount($data['t_account']);
print_r(PHP_EOL . 'pool_key = ' . $pool_key['key']);


$callbackData['hmac'] = $HJAlipayH5->md5sign($callbackData, $pool_key['key']);

$result = $HJAlipayH5->callback($callbackData);
print_r(PHP_EOL . '回调结果:' . $result);
if ($result !== 'success') {
    $HJAlipayH5->fail_print('回调失败');
} else {
    print_r(PHP_EOL . '回调成功');
}

// 再次检查DB
$data = $HJAlipayH5->db_get_by_id($id);

if ($data['id'] != $id) {
    $HJAlipayH5->fail_print('id异常');
}
// 检查上游订单号 $upstream_order_number
if ($data['upstream_order_number'] != $db_upstream_order_number) {
    $HJAlipayH5->fail_print('上游订单号异常');
}
// 检查payment_status 4
if (!in_array($data['payment_status'], ['1', '2', '3'])) {
    $HJAlipayH5->fail_print('payment_status 异常');
}
if (empty($data['payment_time'])) {
    $HJAlipayH5->fail_print('payment_time 为空');
}

