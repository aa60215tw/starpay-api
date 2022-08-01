<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabGeqProvider extends CashFlowProvider implements CashFlowProviderImp
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
            "partner"=>$data["t_mid"],
            "out_trade_no"=>$data["upstream_order_number"],
            "subject"=>$data["my_order_number"],
            "show_url"=>"https://www.rockfintech.com/",
            "body"=>$data["my_order_number"],
            "total_fee"=>$data["pay_amount"],
            "fee_type"=>"1",
            "spbill_create_ip"=> MY_IP,
            "trade_mode"=> '0002',
            "trans_channel"=> 'pc',
            "service" => 'pay_service',
            "service_type"=> $this->getBankcode($data['swift']),
            "imagetype"=> "codeurl",
            "notify_url"=> $data['turn']
        );
        $key = $data['key'];
        $sign =  $this->md5sign($jsapi,$key);
        $jsapi["sign"] = $sign ;
        $jsapi = http_build_query($jsapi);
        $jsapi = urldecode($jsapi);
        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '钜石，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'], $jsapi,'钜石','BabGeq');
        $output = trim($output);

        if ($data['swift'] == "UNIONPAY")
            exit($output);

        $output_array = json_decode($output,true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '钜石，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);

        if($output_array['retcode'] != '0'){
            $output_array['retmsg'] = empty($output_array['retmsg'])?'请求超时':$output_array['retmsg'];
            $error_treat = $this->error_treat('钜石','BabGeq',$output,$data,$this->i,$output_array['retmsg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }
        else{
            if($this->i != 0){
                $this->update_order_number($data);
            }

            $this->poolUpdataTransaction($data);
            $url = $output_array['codeurl'];
            if($data['api_type'] == 1){
                $this->api_out($data,$url);
            }

            if ($data['swift'] == "ALIPAY_APP" || $data['swift'] == "WXPAY_APP") {
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
        switch ($swift){
            case "ALIPAY":
                return "0002";
            case "ALIPAY_APP":
                return "0002";
            case "UNIONPAY":
                return "0003";
            case "WXPAY":
                return "0005";
            case "WXPAY_APP":
                return "0005";
            default:
                return "0002";
        }
    }

        public function getOrder($orderList = array()){
        $jsapi = array(
            "service" => 'query_order_service',
            "out_trade_no" => $orderList['upstream_order_number'],
            "partner" => $orderList['t_mid'],
        );
        $key = $orderList['key'];
        $sign =  $this->md5sign($jsapi,$key);
        $jsapi['sign'] = $sign ;
        $jsapi = http_build_query($jsapi);
        $jsapi = urldecode($jsapi);
        $output = $this->shared->curl($orderList['query'],$jsapi,'钜石','BabGeq');

        $output_array = json_decode($output,true);

        if($output_array['retcode'] != '0')
            return 3;

        $output_array['key'] = $orderList['key'];
        $sign =  $this->md5sign($output_array,$key);

        if ($sign != $output_array['sign'])
            return 2;

        if($output_array['trade_state'] != '0')
            return 1;

        return 0;
    }

    public function md5sign($jsapi,$key)
    {
        $md5str = "";
        ksort($jsapi);
        foreach ($jsapi as $k => $v) {
            if ("" != $v && "sign" != $k) {
                $md5str .= $k . "=" . $v . "&";
            }
        }
        $md5str = substr($md5str, 0, -1).$key;
        $sign = md5($md5str);
        return $sign;
    }

    public function toArray($content)
    {
        $data = array();
        foreach (explode('&', $content) as $couple) {
            list ($key, $val) = explode('=', $couple);
            $data[$key] = $val;
        }
        return $data;
    }
}

?>