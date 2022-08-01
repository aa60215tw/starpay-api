<?php

require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/FormatVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/LackVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/UserVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/OrderNumVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/BankCodeVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/PathVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/Md5Verification.php' );

class OrderQueryVerification
{
    public function __construct($api)
    {
        $this->api = $api;
    }

    public function verification_account()
    {
        $this->api['status'] = 'query';

        $lack_data = LackVerification::verify($this->api);//訂單缺少判斷
        if ($lack_data != NULL)
            return $lack_data;

        $format_data = FormatVerification::verify($this->api);//訂單格式判斷
        if ($format_data != NULL)
            return $format_data;

        $user_data = UserVerification::verify($this->api);//商戶判斷
        if ($user_data['verify_status'] != '1')
            return $user_data;

        $order_data = OrderNumVerification::verify($this->api);//訂單是否存在判斷
        if ($order_data['verify_status'] != '1')
            return $order_data;

        $md5_data = Md5Verification::verify($this->api);//驗簽判斷
        if ($md5_data != NULL)
            return $md5_data;

        $data = array_merge($this->api, $user_data, $order_data);
        $data['returncode'] = '000';
        return $data;
    }

}

?>