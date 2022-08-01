<?php
require_once( FRAMEWORK_PATH . 'extends/Verification/VerificationData.php' );
require_once( FRAMEWORK_PATH . 'collections/PathAccountCollection.php' );
require_once( FRAMEWORK_PATH . 'collections/PathCollection.php' );
require_once( FRAMEWORK_PATH . 'collections/PathBankCollection.php' );

abstract class PathVerification implements VerificationData {

    static public function verify($orderdata) {
        $path_account = new PathAccountCollection();
        $path = new PathCollection();
        $path_bank = new PathBankCollection();
        $path_account_records = $path_account->path_account($orderdata);
        $path_account_data['verify_status'] = '3';
        if (empty($path_account_records['records'])){
            $path_account_data['returncode'] = '商户未开通此通道';
            $path_account_data['message_code'] = '305';
            return $path_account_data;
        }

        $path_account_data = $path_account_records['records'][0];
        if ($path_account_data['status'] != '1'){
            $path_account_data['returncode'] = '通道已禁用';
            $path_account_data['message_code'] = '305';
            return $path_account_data;
        }

        $path_records = $path->path($path_account_data);
        if (empty($path_records['records'])){
            $path_account_data['returncode'] = '商户未开通此通道';
            $path_account_data['message_code'] = '305';
            return $path_account_data;
        }

        if($path_records['records'][0]['status'] != '1'){
            $path_account_data['returncode'] = '通道已禁用';
            $path_account_data['message_code'] = '305';
            return $path_account_data;
        }

        if ($path_account_data['fee_status'] == '2') {
            unset($path_account_data['fixed_fee'], $path_account_data['fixed_feelow'], $path_account_data['fixed_feehigh'], $path_account_data['feelow'], $path_account_data['feehigh'], $path_account_data['fee_type']);
        } else if ($path_account_data['fee_status'] == '1') {
            unset($path_account_data['fee'], $path_account_data['feelow'], $path_account_data['feehigh'], $path_account_data['fixed_feelow'], $path_account_data['fixed_feehigh']);
            $path_account_data['fee'] = $path_account_data['fixed_fee'];
            unset($path_account_data['fixed_fee'], $path_account_data['fee_type']);
        }

        if ($path_account_data['limitlow'] > $orderdata['pay_amount'] || $path_account_data['limithigh'] < $orderdata['pay_amount']) {
            $limitlow = $path_account_data['limitlow'];
            $limithigh = $path_account_data['limithigh'];
            $path_account_data['verify_status'] = '2';
            $path_account_data['returncode'] = "金额错误(最低 $limitlow 最高 $limithigh)";
            $path_account_data['message_code'] = '306';
            return $path_account_data;
        }

        $path_bank_records = $path_bank->getRecord(array('id' => $path_account_data['path_bank_id']));
        if($path_bank_records['address'] != null){
            $path_account_data['address'] =$path_bank_records['address'];
        }else{
            $path_account_data['address'] = $path_records['records'][0]['address1'];
        }
        $path_account_data['obtp_code'] = $path_bank_records['obtp_code'];
        $path_account_data['path_id'] = $path_records['records'][0]['id'];
        $path_account_data['swift_path'] = $path_records['records'][0]['swift_path'];
        $path_account_data['turn'] = $path_records['records'][0]['turn'];
        $path_account_data['verify_status'] = '1';
        return $path_account_data;
    }

}