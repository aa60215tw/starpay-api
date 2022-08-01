<?php
require_once( FRAMEWORK_PATH . 'extends/Verification/VerificationData.php' );

abstract class LackVerification implements VerificationData {

    static public function verify($orderdata) {
        $data = array();
        switch ($orderdata['status']){
            case "pay":
                if(empty($orderdata['user_id']) || empty($orderdata['pay_order_number']) || empty($orderdata['pay_amount']) || empty($orderdata['order_time'])
                    || empty($orderdata['swift']) || empty($orderdata['pay_notifyurl']) || empty($orderdata['pay_md5sign']) || empty($orderdata['currency'])) {
                    $data['returncode'] = '订单资料缺少';
                    $data['message_code'] = '301';
                }else if($orderdata['currency'] != 'CNY'){
                    $data['returncode'] = '币值需为CNY';
                    $data['message_code'] = '301';
                }else if(!is_numeric($orderdata['pay_amount'])){
                    $data['returncode'] = '金额须为数字';
                    $data['message_code'] = '301';
                }
                break;
            case "query":
                if(empty($orderdata['user_id']) || empty($orderdata['pay_order_number']) || empty($orderdata['search_time']) || empty($orderdata['pay_md5sign'])) {
                    $data['returncode'] = '订单资料缺少';
                    $data['message_code'] = '301';
                }else if (date('Y-m-d H:i:s', strtotime($orderdata['search_time'])) != $orderdata['search_time']){
                    $data['returncode'] = '查询时间须为时间格式';
                    $data['message_code'] = '301';
                }
                break;
            default:
                $data['returncode'] = '系统错误(参数错误)';
                $data['message_code'] = '999';
        }

        return $data;
    }

}