<?php
require_once( FRAMEWORK_PATH . 'extends/Verification/VerificationData.php' );
require_once( FRAMEWORK_PATH . 'collections/MemberCollection.php' );

abstract class UserVerification implements VerificationData {

    static public function verify($orderdata) {
        $collection = new MemberCollection();
        $records = $collection->searchRecords($orderdata);
        $member = $records['records'];
        if(empty($member)){
            $data['verify_status'] = '2';
            $data['returncode'] = '商户编号不存在';
            $data['message_code'] = '302';
            return $data;
        }
        $data['user_key'] = $member[0]['Key'];
        $data['member_ip'] = $member[0]['member_ip'];
        $data['area1'] = $member[0]['area1'];
        $data['area2'] = $member[0]['area2'];
        $data['verify_status'] = '1';
        return $data;
    }

}