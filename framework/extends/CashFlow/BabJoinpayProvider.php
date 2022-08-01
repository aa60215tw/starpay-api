<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabJoinpayProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $pool_judgment = '';
        $waitTime = rand(300,500);
        $data["upstream_order_number"] = date("YmdHis");
        $system = new SystemCollection();
        $switch = $system->getRecordsByCondition('`key` in (:key,:key1)', array('key' => "open_hnapay", 'key1' => "hnapay_ratio"), 1, 2);
        $switch_value = $switch['records'][0]['value'];
        $switch_ratio = $switch['records'][1]['value'];

        if($switch_value){
            $path_rand = rand(1, 100);
            if($switch_value == '1'){
                if($path_rand <= $switch_ratio && $data['swift'] == "ALIPAY") {
                    $data['swift_path'] = "Hnapay";
                    $data['path_id'] = "8";
                    $data['turn'] = "http://35.201.254.106/callback/hnapayback.php";
                    $data['address'] = "https://gateway.hnapay.com/website/scanPay.do";
                    $switch_value = true;
                } else {
                    $data['swift'] = "ALIPAY_APP";
                    $switch_value = false;
                }
            } elseif($switch_value == '2'){
                if($path_rand <= $switch_ratio) {
                    $data['user_id'] = "bab00037";
                    $switch_value = false;
                }
            }
        }

        if($data['swift'] == "ALIPAY_APP"){
            $pool = $this->poolAddOrder($data, $pool_judgment, true, $waitTime, true, true);
        }else{
            $pool = $this->poolAddOrder($data, $pool_judgment, true, $waitTime, false, true);
        }
        if (!$pool) {
            return false;
        }
        $data_all = array_merge($data,$pool);

//        if($data_all['key1'] && $data_all['rsakey']){
//            $f2f = new BabF2fProvider();
//            $f2f->send_api($data_all);
//
//        }else{
//            $this->send_api($data_all);
//        }
        if($switch_value) {
            $Hnapay = new HnapayProvider();
            $Hnapay->send_api($data_all);
            exit();
        }

        $this->send_api($data_all);

    }

    public function send_api($data)
    {
        $curlTimeOut = 15;
        $t_mid = $data['t_mid'];
        if ($data['swift'] == "ALIPAY_APP" && !empty($data['alipay_user_id'])) {
            $t_mid = '/js/'. $data['alipay_user_id'] . '/' . $data['t_mid'];
        }

        $jsapi = array(
            "p0_Version" => '1.0',
            "p1_MerchantNo" => $data['t_account'],
            "p2_OrderNo" => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
            "p3_Amount" => sprintf("%.2f",$data['pay_amount']),
            "p4_Cur" => '1',
            "p5_ProductName" => mb_substr($data['stores'], 0,5,"utf-8"). ",业务交易号:" . $data["my_order_number"]. "-" . $data["upstream_times"],
            "p7_Mp" => $t_mid,
            "p9_NotifyUrl" => $data['turn'],
            "q1_FrpCode" => $this->getBankcode($data['swift']),
            'q4_IsShowPic' => '1'
        );

        $signKey = $data['key'];
        $sign =  $this->md5sign($jsapi,$signKey);
        $jsapi['hmac'] = $sign;
        $jsapi = http_build_query($jsapi);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '汇聚，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);
        /*
        switch ($this->i){
            case 1:
                $url = $data['address'];
                break;
            case 2:
                $url = "http://47.52.114.251:2018/relay/zong.php";
                break;
            case 3:
                $url = $data['address'];
                break;
            default:
                $url = $data['address'];
                break;
        }*/
        $url = $data['address'];

        $header = array("Url:". $data['address']);

        $output = $this->shared->curl($url, $jsapi,'汇聚','BabJoinpay', 'post', $header, 'off', $curlTimeOut);

        $output = trim($output);
        $output_array = json_decode($output,true);

        // $output_array = ['ra_Code' => 200, 'rb_CodeMsg' => 'TEST'];

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);

        $msg = '汇聚，上游订单号:' . $data['upstream_order_number'] . '\r\n上游返回字段:'.$output;
        $path_up_log->warn($msg);

        $data['upstream_parameter'] = "入參:$jsapi 出參:$output";
        if($output_array['ra_Code'] != 100){
            $output_array['rb_CodeMsg'] = empty($output_array['rb_CodeMsg'])?'请求超时':$output_array['rb_CodeMsg'];

            if($output_array['rb_CodeMsg'] == "订单二维码请求失败:result_code:FAIL+err_code:FAIL+err_code_des:商户已经被暂停" ||
                $output_array['rb_CodeMsg'] == "订单二维码请求失败:result_code:FAIL+err_code:FAIL+err_code_des:商户不存在或不支持支付类型：alipay_js" ||
                $output_array['rb_CodeMsg'] == "订单二维码请求失败:result_code:FAIL+err_code:FAIL+err_code_des:商户不存在或不支持支付类型：alipay_native" ||
                $output_array['rb_CodeMsg'] == "订单二维码请求失败:result_code:FAIL+err_code:FAIL+err_code_des:商户支付权限未开通"){
                $this->poolStatusChange($data['pool_id'], -3);
                $this->error_msg('汇聚', $output, $data, $output_array['rb_CodeMsg']);
            }

            $error_treat = $this->error_treat('汇聚','BabJoinpay',$output,$data, 10,$output_array['rb_CodeMsg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                return $this->send_api($data);
            }
        }else{
            if($this->i != 1){
                // $this->update_order_number($data);
                $this->update_order_times($data);
            }

            if($data['area1'] != 'other'){
                $this->poolUpdataTransaction($data, 1);
            }

            $url = $output_array['rc_Result'];
            if($data['api_type'] == 1){
                $this->api_out($data,$url);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                return array('url' => $url);

            }

            if($data['api_type'] == 2){
                return array('url' => $url);
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

        return '';
    }

    public function getBankcode($swift){
        switch ($swift){
            case "ALIPAY":
                return "ALIPAY_NATIVE";
            default:
                return "ALIPAY_NATIVE";
        }
    }

    public function getOrder($orderList = array(), $search_again = true){
        $jsapi = array(
            "p1_MerchantNo" => $orderList['t_account'],
            "p2_OrderNo" => $this->shared->concat_upstream_order_number($orderList['upstream_order_number'], $orderList['upstream_times']),
        );
        $signKey = $orderList['key'];
        $sign =  $this->md5sign($jsapi, $signKey);
        $jsapi['hmac'] = $sign ;
        $jsapi = http_build_query($jsapi);

        $output = $this->shared->curl($orderList['query'],$jsapi,'汇聚','BabJoinpay');

        $output_array = json_decode($output,true);

        if ($output_array['rb_Code'] == '10080002') {

            // 验证签名失败时 重打api查询
            $key_array = [
                '888103700002521' => '811edf57252c4423b17c91a11f8ea715',
                '888103700002520' => '87d8ccc5c81a4554b2948f064a3fa03c',
                '888105000005221' => '09b6b50c7489411aa8de08d977b66a65',
                '888105200000607' => 'c82970672494497889fd8087bb5c31dc',
                '888105200000368' => 'b8fa00bf835742f5aec61bc476154441',
            ];
            $merchantNo = $output_array['r1_MerchantNo'];
            if ($search_again && !empty($key_array[$merchantNo])) {
                if ($key_array[$merchantNo] != $orderList['key']) {
                    $orderList['key'] = $key_array[$merchantNo];
                    return $this->getOrder($orderList, false);
                }
            }
        }

        if($output_array['rb_Code'] != 100) {
            return 3;
        }

        $output_array["r3_Amount"] = sprintf("%.2f",$output_array["r3_Amount"]);
        $output_sign = $output_array["hmac"];
        unset($output_array["hmac"]);

        $sign = strtoupper($this->md5sign($output_array, $signKey));

        if ($sign != $output_sign) {
            return 2;
        }

        if($output_array['ra_Status'] != 100)
            return 1;

        return 0;
    }

    public function md5sign($jsapi,$signKey)
    {
        ksort($jsapi);
        $md5str = "";
        foreach ($jsapi as $k=>$v){
            if("" != $v && "hmac" != $k){
                $md5str.= $v;
            }
        }
        $md5str = $md5str.$signKey;
        $sign=md5($md5str);
        return $sign;
    }
}

?>