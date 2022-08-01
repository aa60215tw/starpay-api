<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabpaygwProvider extends CashFlowProvider implements CashFlowProviderImp
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
            'version' =>  "1.1",
            "merchantId" => $data['t_mid'],
            "tradeDate" => date("Ymd"),
            "tradeTime" => date("His"),
            "orderNo" => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
            "amount" => $data['pay_amount']*100,
            "clientIp"   => MY_IP,
            "service"  =>  $this->getBankcode($data['swift']),
            "notifyUrl" => $data['turn'],
            "key" => $data['key'],
        );

        $sign =  $this->md5sign($jsapi);
        $jsapi['sign'] = $sign;
        unset($jsapi['key']);
        $jsapi = http_build_query($jsapi);
        $jsapi = urldecode($jsapi);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '马上富，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);


        $output = $this->shared->curl($data['address'], $jsapi,'马上富','Babpaygw');
        $output_array = $this->toArray($output);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '马上富，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);

        if($output_array['repCode'] != '0001'){
            $output = $this->curl_again($jsapi,$data);
            $output_array = $this->toArray($output);
        }

        if($output_array['repCode'] != '0001'){
            $output_array['repMsg'] = empty($output_array['repMsg'])?'请求超时':$output_array['repMsg'];
            $error_treat = $this->error_treat('马上富','Babpaygw',$output,$data,$this->i,$output_array['repMsg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }
        $url = $output_array['resultUrl'];

        if($url == ''){
            $error_treat = $this->error_treat('马上富','Babpaygw',$output,$data,$this->i,'上游QRCODE地址返回错误');
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
                return "pay.alipay.qrcode";
            case "ALIPAY_APP":
                return "pay.alipay.qrcode";
            case "WXPAY":
                return "pay.weixin.qrcode";
            case "WXPAY_APP":
                return "pay.weixin.wap";
            case "QQPAY":
                return "pay.qq.qrcode";
            case "QQPAY_APP":
                return "pay.qq.qrcode";
            default:
                return "pay.alipay.qrcode";
        }
    }

    public function getOrder($orderList = array()){
        $jsapi = array(
            "version" => "1.1",
            "service" => 'trade.query',
            "tradeDate" => date("Ymd"),
            "tradeTime" => date("His"),
            "orderNo" => $this->shared->concat_upstream_order_number($orderList['upstream_order_number'], $orderList['upstream_times']),
            "merchantId" => $orderList['t_mid'],
            "key" => $orderList['key'],
        );
        $sign =  $this->md5sign($jsapi);
        $jsapi['sign'] = $sign ;
        unset($jsapi['key']);
        $jsapi = http_build_query($jsapi);
        $jsapi = urldecode($jsapi);
        $output = $this->shared->curl($orderList['query'],$jsapi,'马上富','Babpaygw');

        $output_array = $this->toArray($output);

        if($output_array['repCode'] != '0001')
            return 3;

        $output_array['key'] = $orderList['key'];
        $sign =  $this->md5sign($output_array);

        if ($sign != $output_array['sign'])
            return 2;

        if($output_array['resultCode'] != '1')
            return 1;

        return 0;
    }

    public function md5sign($jsapi)
    {
        $md5str = "";
        ksort($jsapi);
        foreach ($jsapi as $k => $v) {
            if ("" != $v && "sign" != $k) {
                $md5str .= $k . "=" . $v . "&";
            }
        }
        $md5str = substr($md5str, 0, -1);
        $sign = md5($md5str);
        return $sign;
    }

    public function toArray($content)
    {
        $output = trim($content);
        parse_str($output,$output_array);
        return $output_array;
    }

    public function curl_again($jsapi,$data)
    {
        $post_jsapi =
            [
                'jsapi' => $jsapi,
                'post_url' => $data['address'],
                'status' => 'post',
            ];
        $url = 'http://47.91.219.201/Server.php';
//        $url = 'http://happytopay.jishenghe168.com/Server.php';
        $post_jsapi = json_encode($post_jsapi,320);
        $output = $this->shared->curl($url, $post_jsapi,'马上富','Babpaygw');
        $output = trim($output);
        return $output;
    }
}

?>