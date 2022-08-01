<?php
require_once(dirname(dirname(__FILE__)) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'collections/MemberCollection.php');
require_once(FRAMEWORK_PATH . 'collections/PayOrderCollection.php');
require_once(FRAMEWORK_PATH . 'extends/Signature/Md5.php');
require_once(FRAMEWORK_PATH . 'extends/CashFlow/CashFlowProvider.php');
$data_post = $_POST;
if($data_post['msg_code'] == '400'){
    $CashFlow = new CashFlowProvider;
    $msg = $data_post['path']. "回调查单支付失败，订单号：". $data_post['pay_order_number'] ;
    $CashFlow->upstream_error_msg($data_post['swift_path'], $msg);
    $error_code = $CashFlow->error_msg($data_post['path'], $msg, $data_post, $data_post['msg'], 'wallet');
    if(!$error_code)
        exit('error');
}
if($data_post['msg_code'] != '1'){
    $CashFlow = new CashFlowProvider;
    $msg = $data_post['path']. "钱包更新失败：". $data_post['pay_order_number'] ;
    $CashFlow->upstream_error_msg($data_post['swift_path'], $msg);
    $error_code = $CashFlow->error_msg($data_post['path'], $msg, $data_post, $data_post['msg'], 'wallet');
    if(!$error_code)
        exit('error');
}
$collection = new MemberCollection;
$userList = $collection->getRecord(array('user_id' => $data_post['user_id']));
$md5Class = new Md5();
$sign = $md5Class->md5sign($userList['Key'].AUTH_KEY,$data_post);
if($sign == $data_post["sign"]){
    $orderCollection = new PayOrderCollection('write_db');
    $orderCollection->get(array('pay_order_number' => $data_post['pay_order_number']))->update(array('settlement_status' => 1));
    exit('success');
}
exit('error');