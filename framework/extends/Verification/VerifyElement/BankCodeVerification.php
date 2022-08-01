<?php
require_once( FRAMEWORK_PATH . 'extends/Verification/VerificationData.php' );
require_once(FRAMEWORK_PATH . 'collections/BankCollection.php');

abstract class BankCodeVerification implements VerificationData {

    static public function verify($orderdata) {
        $bank = new BankCollection();
        $bank_switf_quary = $bank->bank($orderdata);
        $bank_switf = $bank_switf_quary['records'];
        if(empty($bank_switf)) {
            $data['returncode'] = '支付方式错误';
            $data['message_code'] = '304';
            return $data;
        }
    }

}