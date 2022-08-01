<?php

require_once(FRAMEWORK_PATH . 'system/controllers/RestController.php');
require_once(FRAMEWORK_PATH . 'collections/MemberCollection.php' );
require_once(FRAMEWORK_PATH . 'collections/PoolCollection.php');
require_once(FRAMEWORK_PATH . 'collections/PathAccountCollection.php');
require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');

class WalletpayController extends RestController
{
    public function wallet_balance(){
        $shared = new Shared();
        $return_array = $shared->call_wallet($this->receiver);
        $wallet = json_decode($return_array,true);
        return $wallet;
    }

    public function cash(){
        $shared = new Shared();
        $data = $this->receiver;
        $path_account = new PathAccountCollection;
        $path_accountList = $path_account->seach_path_account(array("user_id"=>$data['partner'] , "swift" => $data['swift']));
        $data['data2'] = json_encode($path_accountList,320);
        $return_array = $shared->call_wallet($data);
        $wallet = json_decode($return_array,true);
        return $wallet;
    }

    public function rechargelist(){
        $shared = new Shared();
        $return_array = $shared->call_wallet($this->receiver);
        $wallet = json_decode($return_array,true);
        return $wallet;
    }

    public function cashlist(){
        $shared = new Shared();
        $return_array = $shared->call_wallet($this->receiver);
        $wallet = json_decode($return_array,true);
        return $wallet;
    }
}

?>