<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabGxchwlProvider extends CashFlowProvider implements CashFlowProviderImp
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
        $MD5 = new Md5();
        $jsapi = array(
            "pay_memberid" => $data['t_mid'],
            "pay_applydate" => date("Y-m-d H:i:s"),
            "pay_bankcode" => $this->getBankcode($data['swift']),
            "pay_orderid" => $data['upstream_order_number'],
            "pay_amount"  =>  $data["pay_amount"],
            "pay_notifyurl" => $data['turn'],
            "pay_callbackurl" => $data['turn'],
        );

        $sign =  $MD5->md5sign($data['key'],$jsapi);
        $jsapi['pay_md5sign'] = $sign ;
        $jsapi['pay_productname'] = $data["my_order_number"] ;
        $jsapi['pay_productdesc'] = $data["my_order_number"] ;

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '共享，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'], urldecode(http_build_query($jsapi)),'共享','BabGxchwl');

        $output = trim($output);
        $output_array = json_decode($output,true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '共享，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);

        if($output_array['status'] == "error"){
            $error_treat = $this->error_treat('共享','BabGxchwl',$output,$data,$this->i,$output_array['msg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }

        preg_match_all('/< *img[^>]*src *= *["\']?([^"\']*)/i', $output, $matches);
        $output_img_src = $matches[1][1];
        if(!$output_img_src){
            $error_treat = $this->error_treat('共享','BabGxchwl',$output,$data,$this->i,"请求超时");
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }

        $qrcode_url = $this->shared->imgToUrl($output_img_src);

        if(!$qrcode_url){
            $error_treat = $this->error_treat('共享','BabGxchwl',$output,$data,$this->i,'上游QRCODE地址返回错误');
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }else{
            if($this->i != 0){
                $this->update_order_number($data);
            }

            $this->poolUpdataTransaction($data);

            if($data['api_type'] == 1){
                $this->api_out($data,$qrcode_url);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                header("location:$qrcode_url");
                exit();
            }

            $cash =
                [
                    'ordernumber' => $data['pay_order_number'],
                    'money' => $data['pay_amount'],
                    'banktype' => $data['swift'],
                    'url' => $qrcode_url
                ];
            $this->shared->cash($cash);//轉址收銀台;
        }


    }

    public function getBankcode($swift){
        switch ($swift){
            case "ALIPAY":
                return "953";
            case "ALIPAY_APP":
                return "953";
            default:
                return "953";
        }
    }

    public function getOrder($orderList = array())
    {
        $MD5 = new Md5();
        $jsapi = array(
            "pay_memberid" => $orderList['t_mid'],
            "pay_orderid" => $orderList['upstream_order_number'],
        );
        $sign =  $MD5->md5sign($orderList['key'],$jsapi);
        $jsapi['pay_md5sign'] = $sign ;

        $output = $this->shared->curl($orderList['query'],$jsapi,'马上富','Babpaygw');

        $output_array = json_decode($output,true);

        if($output_array['returncode'] != '00')
            return 3;


        $sign =  $MD5->md5sign($orderList['key'],$output_array);

        if ($sign != $output_array['sign'])
            return 2;

        if($output_array['trade_state'] != 'SUCCESS')
            return 1;

        return 0;
    }
}

?>