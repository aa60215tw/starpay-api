<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(FRAMEWORK_PATH . 'collections/PoolCollection.php');

class SwiftpassalProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $upstream_order_number = $this->shared->new_upstream_order_number($data['user_id']);
        $data["upstream_order_number"] = $upstream_order_number;
        $pool = $this->poolAddOrder($data);
        if (!$pool) {
            return false;
        }
        $data_all = array_merge($data,$pool);
        $this->send_api($data_all);
    }

    public function send_api($data)
    {
        $Md5 = new Md5();
        $jsapi = array(
            "mch_id" => $data['t_mid'],
            'nonce_str' => $data['upstream_order_number'],
            "service" => $this->getBankcode($data['swift']),
            "total_fee" => $data['pay_amount'] * 100,
            "out_trade_no" => $data['upstream_order_number'],
            "notify_url" => $data['turn'],
            "mch_create_ip" => MY_IP,
            "body" => "ALIPAY",
        );
        $sign_md5 = $Md5->md5sign($data['key'], $jsapi);
        $jsapi['sign'] = $sign_md5;
        $xml = $this->toXml($jsapi);
        $output = $this->shared->curl($data['address'],$xml,'威富通','swiftpassal');
        $output_array = $this->parseXML($output);
        $output_json = json_encode($output_array,JSON_UNESCAPED_UNICODE);

        if ($output_array['status'] != 0 || empty($output_array) || $output_array['status'] == '') {
            $this->upstream_error_msg('swiftpassal', $output_json);
            $this->error_msg('威富通',$output_json,$data);
        }

        if ($output_array['result_code'] != 0) {
            $error_treat = $this->error_treat('威富通','swiftpassal',$output_json,$data,$this->i);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        } else {
            if ($this->i != 0) {
                $this->update_order_number($data);
            }

            $sign_md5 = $Md5->md5sign($data['key'], $output_array);
            if ($sign_md5 != $output_array['sign']) {
                $this->upstream_error_msg('swiftpassal',$output_json.'验签错误');
                $this->error_msg('威富通',$output_json.'验签错误',$data);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                $url = $output_array['code_url'];
                header("location:$url");
                exit();

            } else {
                if ($data['api_type'] == 1) {
                    $this->api_out($data,$output_array['code_url']);
                }
                $cash =
                    [
                        'ordernumber' => $data['pay_order_number'],
                        'money' => $jsapi['total_fee'] / 100,
                        'banktype' => $data['swift'],
                        'url' => $output_array['code_url']
                    ];
                $this->shared->cash($cash);//轉址收銀台
            }
        }
    }

    public function getBankcode($swift){
        switch ($swift){
            case "ALIPAY_APP":
                return "pay.alipay.native";
            case "ALIPAY":
                return "pay.alipay.native";
            default:
                return "pay.alipay.native";
        }
    }

    public function getOrder($orderList = array())
    {
        $Md5 = new Md5();
        $jsapi = array(
            "mch_id" => $orderList['t_mid'],
            "service" => "unified.trade.query",
            "nonce_str" => date("yyyyMMddHHmmss"),
            "out_trade_no" => $orderList['upstream_order_number'],
        );
        $sign_md5 = $Md5->md5sign($orderList['key'], $jsapi);
        $jsapi["sign"] = $sign_md5;
        $xml = $this->toXml($jsapi);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $orderList['query']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
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

}