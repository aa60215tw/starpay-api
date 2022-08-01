<?php
require_once( FRAMEWORK_PATH . 'extends/Verification/VerificationData.php' );
require_once(FRAMEWORK_PATH . 'collections/PayOrderCollection.php');

abstract class OrderNumVerification implements VerificationData {

    static public function verify($orderdata) {
        $path_order = new PayOrderCollection();
        $data = null;
        switch ($orderdata['status']){
            case "pay":
                $path_order_records = $path_order->path_order_verify(array('pay_order_number'=>$orderdata['pay_order_number']));
                if(!empty($path_order_records['COUNT'])) {
                    $data['returncode'] = '订单号已存在';
                    $data['message_code'] = '303';
                    return $data;
                }
                break;
            case "query":
                $path_order_records = $path_order->getRecord(array('pay_order_number'=>$orderdata['pay_order_number']));
                if(empty($path_order_records)) {
                    $data['returncode'] = '订单号不存在';
                    $data['message_code'] = '308';
                    $data['verify_status'] = '0';
                    return $data;
                }
                $path_order_records['verify_status'] = '1';
                return $path_order_records;
                break;
            default:
                $data['returncode'] = '系统错误(参数错误)';
                $data['message_code'] = '999';
        }
        return $data;
    }

}