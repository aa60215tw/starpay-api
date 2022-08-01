<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabShouqianbaProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $pool_judgment = 'all';
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
            "terminal_sn" => $data["t_mid"],
            "client_sn" => $data['upstream_order_number'],
            "payway" => $this->getBankcode($data['swift']),
            "total_amount"  => (string) ($data["pay_amount"]*100),
            "subject"=>$data["my_order_number"],
            "operator" =>'bab',
            'notify_url'=>$data['turn']
        );


        $j_params = json_encode($jsapi);
        $sign = Md5($j_params .$data['key']);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '收钱吧，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);


        $header = array(
            "Format:json",
            "Content-Type: application/json",
            "Authorization:".$data['t_mid']. ' ' . $sign
        );
        $output = $this->shared->curl($data['address'], $j_params,'收钱吧','BabShouqianba','post',$header);
        $output_array=json_decode($output,true);

        if($output_array['result_code']!='200' || !$output_array['result_code']){
            $output_array['msg'] = empty($output_array['error_message'])?'请求超时':$output_array['error_message'];
            $error_treat = $this->error_treat('收钱吧','BabShouqianba',$output,$data,$this->i,$output_array['msg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }else{
            $resullt_data=$output_array['biz_response']['data'];
            $qr_url = $resullt_data['qr_code'];
            $match = strpos($qr_url,"alipay");

            if(!$match){
                $error_treat = $this->error_treat('收钱吧','BabShouqianba',$output,$data,$this->i,'上游QRCODE地址返回错误');
                if(!$error_treat['msg']){
                    $this->i++;
                    $data = $error_treat['data'];
                    $this->send_api($data);
                }
            }

            if($this->i != 0){
                $this->update_order_number($data);
            }

            $this->poolUpdataTransaction($data);

            if($data['api_type'] == 1){
                $this->api_out($data,$qr_url);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                header("location:$qr_url");
                exit();
            }

            $cash =
                [
                    'ordernumber' => $data['pay_order_number'],
                    'money' => $data['pay_amount'],
                    'banktype' => $data['swift'],
                    'url' => $qr_url
                ];
            $this->shared->cash($cash);//轉址收銀台;
        }


    }

    public function getBankcode($swift){
        switch ($swift){
            case "ALIPAY":
                return "1";
            case "WXPAY":
                return "2";
            default:
                return "1";
        }
    }

    public function getOrder($orderList = array())
    {
        $jsapi = array(
            "terminal_sn" => $orderList['t_mid'],
            "client_sn" => $orderList['upstream_order_number']
        );

        $j_params = json_encode($jsapi);
        $sign = Md5($j_params .$orderList['key']);

        $header = array(
            "Format:json",
            "Content-Type: application/json",
            "Authorization:".$orderList['t_mid']. ' ' . $sign
        );
        $output = $this->shared->curl($orderList['query'], $j_params,'收钱吧','BabShouqianba','post',$header);
        $output_array=json_decode($output,320);

        if($output_array['result_code']=='200') {
            $result_data=$output_array['biz_response']['data'];
            switch ($result_data['order_status']) {
                case "CREATED"://还未支付
                    return 1;
                    break;
                case "PAID"://支付成功
                    return 0;
                    break;
                default :
                    return 2;
            }
        }
        return 3;
    }
}



?>