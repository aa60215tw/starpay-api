<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class HnapayProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $pool_judgment = 'user_id';
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
            "tranCode" => "WS01",
            "version" => "2.1",
            "merId" => $data['t_account'],
            "submitTime" => date("YmdHis"),
            "merOrderNum" => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
            "tranAmt" => $data['pay_amount']*100,
            "payType"   => "QRCODE_B2C",
            "orgCode"  =>  $this->getBankcode($data['swift']),
            "notifyUrl" => $data['turn']. "?tmid=". $data['t_mid'],
            "charset" => '1',
            "signType" => "1"
        );
        $rsastr = $this->getpayData($jsapi);

        $Rsa = new Rsa();
        $rsasign = $Rsa->rsa_sign($rsastr,$data['rsakey'],$OPENSSL=OPENSSL_ALGO_SHA1,$charset='bin2hex');
        $jsapi['goodsName'] = mb_substr($data['stores'], 0,5,"utf-8"). ",业务交易号:" . $data["my_order_number"];
        $jsapi['goodsDetail'] =  mb_substr($data['stores'], 0,5,"utf-8"). ",业务交易号:" . $data["my_order_number"];
        $jsapi['tranIP'] ='211.75.237.89';
        $jsapi['weChatMchId'] = $data['t_mid'];
        $jsapi['signMsg'] = $rsasign ;

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '新生，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'],$jsapi,'新生','hnapay');
        $output_array = json_decode($output , true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '新生，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);

        if($output_array['resultCode'] != '0000'){
            $error_treat = $this->error_treat('新生','hnapay',$output,$data,$this->i,$output_array['msgExt']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }else{
            if($this->i != 0){
                $this->update_order_times($data);
            }

            $jsapi=
                [
                    'tranCode' => $output_array['tranCode'],
                    'version' => $output_array['version'],
                    'merId' => $output_array['merId'],
                    'merOrderNum' => $output_array['merOrderNum'],
                    'tranAmt' => $output_array['tranAmt'],
                    'submitTime' => $output_array['submitTime'],
                    'qrCodeUrl' => $output_array['qrCodeUrl'],
                    'hnapayOrderId' => $output_array['hnapayOrderId'],
                    'resultCode' => $output_array['resultCode'],
                    'charset' => $output_array['charset'],
                    'signType' => $output_array['signType'],
                ];
            $rsastr = $this->getpayData($jsapi);
            $rsasign = $Rsa->rsa_verify($rsastr,$data,$output_array['signMsg'],$OPENSSL=OPENSSL_ALGO_SHA1,$charset='bin2hex');
            if (!$rsasign) {
                $this->upstream_error_msg('hnapay',$output.'验签错误');
                $this->error_msg('新生',$output.'验签错误',$data,'上游返回字段验签错误');
            }

            $this->poolUpdataTransaction($data, 1);
            $qr = explode("?",$output_array['qrCodeUrl']);
            $qr1 = explode("=",$qr['1']);
            $qr2 = explode("&",$qr1['1']);
            $url = $qr2[0];

            if($data['api_type'] == 1){
                $this->api_out($data,$url);
            }

            if ($data['swift'] == "ALIPAY_APP" || $data['swift'] == "QQPAY_APP") {
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
                return "ALIPAY";
            case "ALIPAY_APP":
                return "ALIPAY";
            case "QQPAY":
                return "TENPAY";
            case "QQPAY_APP":
                return "TENPAY";
            default:
                return "ALIPAY";
        }
    }

    public function getOrder($orderList = array()){
        $jsapi = array(
            "version" => "2.7",
            "serialID" => date("YmdHis")    ,
            "mode" => '1',
            "type" => '1',
            "orderID" => $this->shared->concat_upstream_order_number($orderList['upstream_order_number'], $orderList['upstream_times']),
            "beginTime" => '',
            "endTime" => '',
            "partnerID" => $orderList['t_account'],
            "remark" => '',
            "charset" => '1',
            "signType" => "1"
        );
        $rsastr = $this->getStringData($jsapi);
        $Rsa = new Rsa();
        $rsasign = $Rsa->rsa_sign($rsastr,$orderList['rsakey'],$OPENSSL=OPENSSL_ALGO_SHA1,$charset='bin2hex');
        $jsapi['signMsg'] = $rsasign ;
        $output = $this->shared->curl($orderList['query'],$jsapi,'新生','hnapay');
        $output_array = $this->http_build_url($output);
        if($output_array['resultCode'] != '0000' || $output_array['queryDetailsSize'] != '1')
            return 3;

        $output_queryDetails = explode(",",$output_array['queryDetails']);

        if($output_queryDetails['6'] != '2')
            return 1;

        return 0;
    }

    public function getStringData($params) {
        $fieldString = "";
        foreach ($params as $key=>$val){
            $fieldString .= $key."=".$val."&";
        }
        return substr($fieldString, 0, -1) ;
    }

    public function getpayData($params) {
        $rsastr = "";
        foreach ($params as $key => $val)
        {
            $rsastr = $rsastr . $key . "=[" . $val."]";
        }
        return $rsastr;
    }

    public function http_build_url($url_arr){
        $new_arr = [];
        $tmp_arr = explode('&', $url_arr);
        foreach ($tmp_arr as $item){
            $url_key_url = explode("=", $item);
            $new_arr[$url_key_url[0]] = $url_key_url[1];
        }
        return $new_arr;
    }
}

?>