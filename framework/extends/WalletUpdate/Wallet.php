<?php
require_once( FRAMEWORK_PATH . 'controllers/MultipayController.php' );
require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');

class Wallet
{
    public function wallet_api($walletData)
    {
        $shared = new Shared();
        $member= new MemberCollection();
//        $path= new PathCollection();
//        $Md5 = new Md5();
        $walletData['order_status'] = $walletData['payment_status'];
        $member_data = $member->getRecord(array('user_id'=>$walletData['user_id']));
        $walletData_new = array(
            "memberid" => $walletData['user_id'],
            "key" => $member_data['Key'],
            "path" => $walletData['swift_path'],
            "pay_order_number" => $walletData['pay_order_number'],
            "upstream_order_number" => $walletData['upstream_order_number'],
            "amount" => $walletData['pay_amount'],
            "fee" => $walletData['fee'],
            "swift" => $walletData['swift'],
            "userid" => $walletData['user_id'],
            "privatekey" => $member_data['Key'],
        );
//        $walletData_new = array_merge($walletData, $path_data);
//        ksort($walletData_new);
//        $walletData_new['path'] = $walletData_new['swift_path'];
//        $walletData_new_post =
//            [
//                'partner' => $walletData_new['user_id'],
//                'op_status' => 'recharge',
//                'data' => json_encode($walletData_new,320),
//                'currency' => 'CNY',
//            ];
//
//        $sign_md5 = $Md5->md5sign($member_data['Key'], $walletData_new_post);
//        $walletData_new_post['sign'] = $sign_md5;
//        $walletData = json_encode($walletData,320);
//        $walletData_base = base64_encode($walletData);
//        $walletData_substr_header = substr($walletData_base, 0,10);
//        $walletData_substr_footer = substr($walletData_base, 10);
//        $walletData_new = substr_replace($walletData_substr_header, $walletData_substr_footer, 0, 0);
//        $walletData_post =
//            [
//                'walletData' => $walletData_new,
//            ];
        $shared->call_curl(http_build_query($walletData_new),WALLET_URL);
    }
}