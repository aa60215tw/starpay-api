<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabTransferProvider extends CashFlowProvider implements CashFlowProviderImp
{
    protected $md5_class;

    public function __construct()
    {
        parent::__construct();
        $this->md5_class = new Md5();
    }

    public function send($data)
    {
        $pool_judgment = 'single';
        $upstream_order_number = $this->shared->new_upstream_order_number($data['user_id']);
        $data["upstream_order_number"] = $upstream_order_number;
        $pool = $this->poolAddOrder($data,$pool_judgment);
        if (!$pool) {
            return false;
        }
        $data_all = array_merge($data,$pool);
        $this->send_api($data_all);
    }

    public function send_api($data)
    {

        $jsapi = array(
            "card_no" => $data['t_mid'],
            "order_id" => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
            "amount" => $data['pay_amount'],
            "callback_url" => $data['turn']
        );

        $sign = $this->md5_class->md5sign($data['key'], $jsapi);
        $jsapi['sign'] = $sign ;
        $jsapi = json_encode($jsapi,320);
        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '转账银行卡，送往上游字段:'.$jsapi;
        $path_our_log->warn($msg);
        $header = array(
            'Content-Type: application/json'
        );
        $output = $this->shared->curl($data['address'],$jsapi,'转账银行卡','BabTransfer', 'post', $header);
        $output_array = json_decode($output , true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '转账银行卡，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);

        if($output_array['msg_code'] != '1'){
            $error_treat = $this->error_treat('转账银行卡','BabTransfer',$output,$data,$this->i,$output_array['msgExt']);

            if($output_array['msg'] == "卡号不存在"){
                $this->poolStatusChange($data['pool_id'], -3);
                $this->error_msg('转账银行卡', $output, $data, $output_array['msg']);
            }

            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }else{
            if($this->i != 0){
                $this->update_order_times($data);
            }

            $this->poolUpdataTransaction($data);
            $url = "alipays://platformapi/startapp";
            $url_data = array(
                "appId" => '09999988',
                "actionType" => 'toCard',
                "sourceId" => 'bill',
                "cardNo" => $data['t_mid'],
                "bankAccount" => $output_array['data']['acc_name'],
                "money" => $output_array['data']['amount'],
                "amount" => $output_array['data']['amount'],
                "bankMark" => $data['t_account'],
            );
            $url = $url.'?'.urldecode(http_build_query($url_data));

            if($data['api_type'] == 1){
                $this->api_out($data,$url);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                header("location:$url");
                exit();
            }

            $cash =
                [
                    'ordernumber' => $data['pay_order_number'],
                    'money' => $data['pay_amount'],
                    'banktype' => $data['swift'],
                    'url' => $url
                ];
            $this->shared->cash($cash);//轉址收銀台;
        }

    }

    public function getBankcode($swift){
    }

    public function getOrder($orderList = array()){
        $jsapi = array(
            "card_no" => $orderList['t_mid'].'123',
            "order_id" =>  $this->shared->concat_upstream_order_number($orderList['upstream_order_number'], $orderList['upstream_times'])
        );

        $sign = $this->md5_class->md5sign($orderList['key'], $jsapi);
        $jsapi['sign'] = $sign ;
        $jsapi = json_encode($jsapi,320);
        $header = array(
            'Content-Type: application/json'
        );
        $output = $this->shared->curl($orderList['query'],$jsapi,'转账银行卡','BabTransfer', 'post', $header);

        $output_array = json_decode($output , true);
        if($output_array['msg_code'] != '1')
            return 3;

        if($output_array['data']['status'] != '1')
            return 1;

        return 0;
    }

}

?>