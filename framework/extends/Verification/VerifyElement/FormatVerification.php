<?php
require_once( FRAMEWORK_PATH . 'extends/Verification/VerificationData.php' );

abstract class FormatVerification implements VerificationData {

    static public function verify($orderdata) {
        $order_number_strlen = strlen($orderdata['pay_order_number']);
        if($order_number_strlen > 32 ){
            $data['returncode'] = '订单编号格式错误，大于32位';
            $data['message_code'] = '300';
            return $data;
        }
    }

}