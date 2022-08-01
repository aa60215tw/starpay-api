<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(FRAMEWORK_PATH . 'collections/PoolCollection.php');

class TestProvider extends CashFlowProvider implements CashFlowProviderImp
{
    public function send($data)
    {
        $pool_judgment = 'all';
        $upstream_order_number = $this->shared->new_upstream_order_number($data['user_id']);
        $data["upstream_order_number"] = $upstream_order_number;
        $pool = $this->poolAddOrder($data,$pool_judgment);
        if(!$pool){
            return false;
        }

        $status = rand(0, 1);
        if ($status == 1) {
            $jsapi = array(
                "orderid" => $data['upstream_order_number'],
                "pay_result" => "1",
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $data['turn']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsapi);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要加這行才能抓到值
            $output = curl_exec($ch);
            curl_close($ch);
            $this->api_out($data,$output);
            return true;
        }
        return false;
    }

    public function getBankcode($swift)
    {
        return ;
    }

    public function getOrder($orderList = array())
    {
        return ;
    }
}