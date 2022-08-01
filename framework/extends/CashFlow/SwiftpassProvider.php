<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(FRAMEWORK_PATH . 'collections/PoolCollection.php');

class SwiftpassProvider extends CashFlowProvider implements CashFlowProviderImp
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
        $Md5 = new Md5();
        $SwiftpassProvider = new SwiftpassProvider();
        $swift_switch = call_user_func_array(array($SwiftpassProvider,$data['swift']), array($data,$data,$status = "pay"));//選擇不同之付方式function的方法
        $sign_md5 = $Md5->md5sign($swift_switch['key'], $swift_switch);
        unset($swift_switch['key']);
        $swift_switch['sign'] = $sign_md5;
        $xml = $this->toXml($swift_switch);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '威富通，送往上游字段:'.json_encode($swift_switch,320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'],$xml,'威富通','swiftpass');

        $output_array = $this->parseXML($output);
        $output_json = json_encode($output_array,320);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '威富通，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.$output_json;
        $path_up_log->warn($msg);

        if ($output_array['status'] != 0 || empty($output_array) || $output_array['status'] == '') {
            $this->upstream_error_msg('swiftpass',$output_json);
            $this->error_msg('威富通',$output_json,$data,$output_array['err_msg']);
        }

        if($output_array['result_code'] != 0){
            $error_treat = $this->error_treat('威富通','swiftpass',$output,$data,$this->i,$output_array['err_msg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        } else {
            if($this->i != 0){
                $this->update_order_number($data);
            }

            $sign_md5 = $Md5->md5sign($data['key'], $output_array);
            if ($sign_md5 != $output_array['sign']) {
                $this->upstream_error_msg('swiftpass',$output.'验签错误');
                $this->error_msg('威富通',$output_json.'验签错误',$data , "上游返回字段验签错误");
            }

            if($data['swift'] == "ALIPAY_APP" || $data['swift'] == "WXPAY_APP" || $data['swift'] == "QQPAY_APP") {
                $url = $output_array['code_url'];
                header("location:$url");
                exit();
            }
            if($data['swift'] == "JDPAY_APP") {
                $url = $output_array['pay_url'];
                header("location:$url");
                exit();
            }else{
                if($data['api_type'] == 1){
                    $this->api_out($data,$output_array['code_url']);
                }
                $cash =
                    [
                        'ordernumber' => $data['pay_order_number'],
                        'money' => $swift_switch['total_fee']/100,
                        'banktype' => $data['swift'],
                        'url' => $output_array['code_url']
                    ];
                $this->shared->cash($cash);//轉址收銀台
            }
        }
    }

    function WXPAY($data,$pool,$status){
        if($status == "pay") {
            $jsapi = array(
                "mch_id" => $pool['t_mid'],
                'nonce_str' => $data['upstream_order_number'],
                "service" => $this->getBankcode($data['swift']),
                "total_fee" => $data['pay_amount']*100,
                "out_trade_no" => $data['upstream_order_number'],
                "notify_url" => $data['turn'],
                "mch_create_ip" => MY_IP,
                "body" => "WXPAY",
                'key' => $pool['key']
            );
            return $jsapi;
        }
    }

    function WXPAY_APP($data,$pool,$status){
        if($status == "pay") {
            $jsapi = array(
                "mch_id" => $pool['t_mid'],
                'nonce_str' => $data['upstream_order_number'],
                "service" => $this->getBankcode($data['swift']),
                "total_fee" => $data['pay_amount']*100,
                "out_trade_no" => $data['upstream_order_number'],
                "notify_url" => $data['turn'],
                "mch_create_ip" => MY_IP,
                "body" => "WXPAY_APP",
                "device_info" => "AND_WAP",
                "mch_app_name" => "API支付",
                "mch_app_id" => "http://192.168.0.244/api_git/api/payment_test",
                'key' => $pool['key']
            );
            return $jsapi;
        }
    }

    function ALIPAY_APP($data,$pool,$status){
        if($status == "pay") {
            $jsapi = array(
                "mch_id" => $pool['t_mid'],
                'nonce_str' => $data['upstream_order_number'],
                "service" => $this->getBankcode($data['swift']),
                "total_fee" => $data['pay_amount']*100,
                "out_trade_no" => $data['upstream_order_number'],
                "notify_url" => $data['turn'],
                "mch_create_ip" => MY_IP,
                "body" => "ALIPAY",
                'key' => $pool['key']
            );
            return $jsapi;
        }
    }

    function ALIPAY($data,$pool,$status){
        if($status == "pay") {
            $jsapi = array(
                "mch_id" => $pool['t_mid'],
                'nonce_str' => $data['upstream_order_number'],
                "service" => $this->getBankcode($data['swift']),
                "total_fee" => $data['pay_amount']*100,
                "out_trade_no" => $data['upstream_order_number'],
                "notify_url" => $data['turn'],
                "mch_create_ip" => MY_IP,
                "body" => "ALIPAY",
                'key' => $pool['key']
            );
            return $jsapi;
        }
    }

    function QQPAY_APP($data,$pool,$status){
        if($status == "pay") {
            $jsapi = array(
                "mch_id" => $pool['t_mid'],
                'nonce_str' => $data['upstream_order_number'],
                "service" => $this->getBankcode($data['swift']),
                "total_fee" => $data['pay_amount']*100,
                "out_trade_no" => $data['upstream_order_number'],
                "notify_url" => $data['turn'],
                "mch_create_ip" => MY_IP,
                "body" => "QQPAY_APP",
                'key' => $pool['key']
            );
            return $jsapi;
        }
    }

    function JDPAY_APP($data,$pool,$status){
        if($status == "pay") {
            $jsapi = array(
                "mch_id" => $pool['t_mid'],
                'nonce_str' => $data['upstream_order_number'],
                "service" => $this->getBankcode($data['swift']),
                "total_fee" => $data['pay_amount']*100,
                "out_trade_no" => $data['upstream_order_number'],
                "notify_url" => $data['turn'],
                "mch_create_ip" => MY_IP,
                "body" => "JDPAY_APP",
                'key' => $pool['key']
            );
            return $jsapi;
        }
    }

    function JDPAY($data,$pool,$status)
    {
        if ($status == "pay") {
            $jsapi = array(
                "mch_id" => $pool['t_mid'],
                'nonce_str' => $data['upstream_order_number'],
                "service" => $this->getBankcode($data['swift']),
                "total_fee" => $data['pay_amount'] * 100,
                "out_trade_no" => $data['upstream_order_number'],
                "notify_url" => $data['turn'],
                "mch_create_ip" => MY_IP,
                "body" => "JDPAY",
                'key' => $pool['key']
            );
            return $jsapi;
        }
    }

    function QQPAY($data,$pool,$status){
        if($status == "pay") {
            $jsapi = array(
                "mch_id" => $pool['t_mid'],
                'nonce_str' => $data['upstream_order_number'],
                "service" => $this->getBankcode($data['swift']),
                "total_fee" => $data['pay_amount']*100,
                "out_trade_no" => $data['upstream_order_number'],
                "notify_url" => $data['turn'],
                "mch_create_ip" => MY_IP,
                "body" => "QQPAY",
                'key' => $pool['key']
            );
            return $jsapi;
        }
    }

    function UNIONPAY($data,$pool,$status){
        if($status == "pay") {
            $jsapi = array(
                "mch_id" => $pool['t_mid'],
                'nonce_str' => $data['upstream_order_number'],
                "service" => $this->getBankcode($data['swift']),
                "total_fee" => $data['pay_amount']*100,
                "out_trade_no" => $data['upstream_order_number'],
                "notify_url" => $data['turn'],
                "mch_create_ip" => MY_IP,
                "body" => "UNIONPAY",
                'key' => $pool['key']
            );
            return $jsapi;
        }
    }

    public function getBankcode($swift){
        switch ($swift){
            case "WXPAY_APP":
                return "pay.weixin.native";
            case "ALIPAY_APP":
                return "pay.alipay.native";
            case "QQPAY_APP":
                return "pay.tenpay.native";
            case "JDPAY_APP":
                return "pay.jdpay.jspay";
            case "ALIPAY":
                return "pay.alipay.native";
            case "WXPAY":
                return "pay.weixin.native";
            case "QQPAY":
                return "pay.tenpay.native";
            case "JDPAY":
                return "pay.jdpay.native";
            case "UNIONPAY":
                return "pay.unionpay.native";
            default:
                return "pay.weixin.wappay";
        }
    }

    public function getOrder($orderList = array())
    {
        $Md5 = new Md5();
        $jsapi = array(
            "mch_id" => $orderList['t_mid'],
            "service" => "unified.trade.query",
            "nonce_str" => mt_rand(time(), time() + rand()),
            "out_trade_no" => $orderList['upstream_order_number'],
        );
        $sign_md5 = $Md5->md5sign($orderList['key'], $jsapi);
        $jsapi["sign"] = $sign_md5;
        $xml = $this->toXml($jsapi);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $orderList['query']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);//要加這行才能抓到值
        $output = curl_exec($ch);
        curl_close($ch);
        $output_array =  $this->parseXML($output);
        $sign_md5 = $Md5->md5sign($orderList['key'], $output_array);
        if($output_array['status'] == "0" && $output_array['result_code'] == "0" && $sign_md5 == $output_array['sign']) {
            switch ($output_array['trade_state']) {
                case "NOTPAY":
                    return 1;
                    break;
                case "SUCCESS":
                    return 0;
                    break;
                default:
                    return 2;
            }
        }
        return 3;
    }

    public function toXml($array){
        $xml = '<xml>';
        forEach($array as $k=>$v){
            $xml.='<'.$k.'><![CDATA['.$v.']]></'.$k.'>';
        }
        $xml.='</xml>';
        return $xml;
    }

    public function parseXML($xmlSrc){
        if(empty($xmlSrc)){
            return false;
        }
        $array = array();
        $xml = simplexml_load_string($xmlSrc);
        $encode = $this->getXmlEncode($xmlSrc);
        if($xml && $xml->children()) {
            foreach ($xml->children() as $node){
                //有子节点
                if($node->children()) {
                    $k = $node->getName();
                    $nodeXml = $node->asXML();
                    $v = substr($nodeXml, strlen($k)+2, strlen($nodeXml)-2*strlen($k)-5);

                } else {
                    $k = $node->getName();
                    $v = (string)$node;
                }

                if($encode!="" && $encode != "UTF-8") {
                    $k = iconv("UTF-8", $encode, $k);
                    $v = iconv("UTF-8", $encode, $v);
                }
                $array[$k] = $v;
            }
        }
        return $array;
    }

    public function getXmlEncode($xml) {
        $ret = preg_match ("/<?xml[^>]* encoding=\"(.*)\"[^>]* ?>/i", $xml, $arr);
        if($ret) {
            return strtoupper ( $arr[1] );
        } else {
            return "";
        }
    }

    public function sign_md5($swift_switch){
        $signSource = "";
        ksort($swift_switch);
        foreach($swift_switch as $k => $v) {
            if("" != $v && "sign" != $k && "key" != $k) {
                $signSource .= $k . "=" . $v . "&";
            }
        }
        $signSource .= "key=" . $swift_switch['key'];
        $sign = strtoupper(md5($signSource));
        return $sign;
    }
}