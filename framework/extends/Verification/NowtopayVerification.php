<?php

require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/FormatVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/LackVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/UserVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/OrderNumVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/BankCodeVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/PathVerification.php' );
require_once( FRAMEWORK_PATH . 'extends/Verification/VerifyElement/Md5Verification.php' );

class NowtopayVerification
{
    public function __construct($api)
    {
        //__construct 建構子，當外部產生這個class的時候，會執行內部的方法。
        $this->api = $api;
        //$this->api 全域變數
    }

    /**
     * @return array
     *  path = 通道名稱
     *  fee_status = 手續費狀態
     *  name = 支付方式名稱
     *  fee = 手續費
     *  limitlow = 限額最低
     *  limithigh = 限額最高
     *  user_key = 帳戶密鑰
     *  returncode = 交易狀態
     */
    public function verification_account()
    {
        $this->api['status'] = 'pay';

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
        if ($order_data != NULL)
            return $order_data;

        $bank_data = BankCodeVerification::verify($this->api);//銀行編碼判斷
        if ($bank_data != NULL)
            return $bank_data;

        $path_data = PathVerification::verify($this->api);//通道判斷
        if ($path_data['verify_status'] != '1')
            return $path_data;

        $md5_data = Md5Verification::verify($this->api);//驗簽判斷
        if ($md5_data != NULL)
            return $md5_data;

        unset($path_data['id'], $path_data['swift'], $path_data['picture'], $path_data['verification_path'], $path_data['status']);
        $data = array_merge($this->api, $path_data, $user_data);
        $data['returncode'] = '000';
        return $data;
    }

}

?>