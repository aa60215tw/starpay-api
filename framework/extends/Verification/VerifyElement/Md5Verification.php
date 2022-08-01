<?php
require_once( FRAMEWORK_PATH . 'extends/Verification/VerificationData.php' );
require_once(FRAMEWORK_PATH . 'collections/MemberCollection.php');

abstract class Md5Verification implements VerificationData {

    static public function verify($orderdata) {
        $collection = new MemberCollection();
        $multipay = new MultipayController();
        $records = $collection->searchRecords($orderdata);
        $member = $records['records'][0];
        $user_key = $member['Key'];
        $order_md5 = $orderdata['pay_md5sign'];
        $api_1 =
            [
                $multipay->user_id => $orderdata['user_id'],
                $multipay->banktype => $orderdata['banktype']??"",
                $multipay->pay_amount => $orderdata['pay_amount']??"",
                $multipay->pay_order_number => $orderdata['pay_order_number']??"",
                $multipay->pay_notifyurl => $orderdata['pay_notifyurl']??"",
                $multipay->currency  => $orderdata['currency']??"",
                $multipay->search_time  => $orderdata['search_time']??""
            ];

        ksort($api_1);
        $md5str = "";
        foreach ($api_1 as $key => $val)
        {
            if("" != $val && "sign" != $key && "key" != $key) {
                $md5str = $md5str . $key . "=" . $val . "&";
            }
        }

        $md5 = strtoupper(md5($md5str . $user_key));

        if($order_md5 != $md5) {
            $data['returncode'] = '签名验证失败';
            $data['message_code'] = '307';
            return $data;
        }
    }

}