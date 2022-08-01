<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(FRAMEWORK_PATH . 'collections/PoolCollection.php');

class BabHuaxingProvider extends CashFlowProvider implements CashFlowProviderImp
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
        $Shared=new Shared();
        $key=$data['key'];
        $channelCode=$data['t_pass'];
        $pubkey=$data['key1'];
        $trscode='AGT0002';//交易码
        $daytime=date('Ymd');
        $day_min_time=date('His');
        $channelFlow=$channelCode.$daytime.'001'.$Shared->randtext(6);

        //xml开头报文
        $header_xml=array(
            'channelCode'=>$channelCode,
            'channelFlow'=>$channelFlow,
            'channelDate'=>$daytime,
            'channelTime'=>$day_min_time,
            'encryptData'=>'');

        $jsapi = array(
            'trscode'=>$trscode,
            'merchantNo'=>$data['t_mid'],
            'payMethod'=>$this->getBankcode($data['swift']),
            'orderNo'=>$data["upstream_order_number"],
            'amount'=>$data['pay_amount'],
            "subject"=>$data["my_order_number"],
            "desc"=>$data["my_order_number"],
            'notifyUrl'=>$data['turn']
        );
        ksort($jsapi);
        $payjson=json_encode($jsapi);
        $sign=base64_encode($this->sign3des($payjson,$key));

        $xml = "<?xml version='1.0' encoding='UTF-8'?><Document>";
        $xmlHeader = $this->toXml($header_xml,'header');

        $body_xml=array(
            'TRANSCODE'=>'OGW00294',
            'XMLPARA'=>$sign
        );
        $xmlbody = $this->toXml($body_xml,'body');
        $xmlbody.="</Document>";

        $all_xml=$xml.$xmlHeader.$xmlbody;
        $md5_xml=strtoupper(md5($all_xml));

        $rsa=$this->private_encrypt($md5_xml,$data['rsakey'],$charset='bin2hex');

        $curl_data="001X11          00000256".strtoupper($rsa).$all_xml;

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '华兴银行，送往上游字段:'.$curl_data;
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'], $curl_data,'华兴银行','BabHuaxing');
        $output = trim($output);

        $filter_str=str_replace("001X11          00000256",'',$output);
        $return_rsa=substr($filter_str,0,256);
        $return_xml=str_replace($return_rsa,'',$filter_str);

        $pXML=$this->parseXML($return_xml);

        $decrypted=$pXML["body"];
        $rsacode= strip_tags($decrypted);
        $dejson=$this->desing3des($rsacode,$key);

        $result_json=strip_tags($dejson);
        $TestStr = preg_replace('/\s(?=)/', '', $result_json);
        $json_clean2=str_replace('&quot;','"',$TestStr);
        $result_data=json_decode($json_clean2,true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '华兴银行，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($result_data,320);
        $path_up_log->warn($msg);

        if ($result_data['respCode'] != '000000'){
            $error_treat = $this->error_treat('华兴银行','BabHuaxing',$result_json,$data,$this->i,$result_data['respMsg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }else{
            if ($this->i != 0) {
                $this->update_order_number($data);
            }
            //解码返回的rsa
            $rsa_decode=$this->public_decrypt($return_rsa,$pubkey);
            if($rsa_decode!=strtoupper(md5($return_xml))){
                $this->upstream_error_msg('BabHuaxing',$result_json.'验签错误');
                $this->error_msg('华兴银行',$result_json.'验签错误',$data,'上游返回字段验签错误');
            }

            $this->poolUpdataTransaction($data);

            $url = $result_data['codeUrl'];
            if ($data['api_type'] == 1) {
                $this->api_out($data,$url);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                header("location:$url");
                exit();
            }

            $cash =
                [
                    'ordernumber' => $data['pay_order_number'],
                    'money' => $jsapi['amount'],
                    'banktype' => $data['swift'],
                    'url' => $url
                ];
            $this->shared->cash($cash);//轉址收銀台
            }
    }

    public function getBankcode($swift){
        switch ($swift){
            case "ALIPAY":
                return "ALIPAY";
            case "WXPAY":
                return "WXPAY";
            default:
                return "ALIPAY";
        }
    }

    public function getOrder($orderList = array())
    {
        $Shared=new Shared();
        $key=$orderList['key'];
        $channelCode=$orderList['t_pass'];
        $pubkey=$orderList['key1'];
        $trscode='AGT0006';//交易码
        $daytime=date('Ymd');
        $day_min_time=date('His');
        $channelFlow=$channelCode.$daytime.'001'.$Shared->randtext(6);

        $header_xml=array(
            'channelCode'=>$channelCode,
            'channelFlow'=>$channelFlow,
            'channelDate'=>$daytime,
            'channelTime'=>$day_min_time,
            'encryptData'=>'');

        $jsapi=array(
            'trscode'=>$trscode,
            'merchantNo'=>$orderList['t_mid'],
            'orderNo'=>$orderList['upstream_order_number']
        );

        ksort($jsapi);
        //json资料签名
        $payjson=json_encode($jsapi);
        $sign=base64_encode($this->sign3des($payjson,$key));

        $xml = "<?xml version='1.0' encoding='UTF-8'?><Document>";
        $xmlHeader = $this->toXml($header_xml,'header');

        $body_xml=array(
            'TRANSCODE'=>'OGW00294',
            'XMLPARA'=>$sign
        );
        $xmlbody = $this->toXml($body_xml,'body');
        $xmlbody.="</Document>";
        $all_xml=$xml.$xmlHeader.$xmlbody;
        $md5_xml=strtoupper(md5($all_xml));
        $rsa=$this->private_encrypt($md5_xml,$orderList['rsakey'],$charset='bin2hex');

        $curl_data="001X11          00000256".strtoupper($rsa).$all_xml;
        $output =$this->shared->curl($orderList['query'], $curl_data,'华兴银行','BabHuaxing');

        $filter_str=str_replace("001X11          00000256",'',$output);
        $return_rsa=substr($filter_str,0,256);
        $return_xml=str_replace($return_rsa,'',$filter_str);
        //解码返回的rsa
        $rsa_decode=$this->public_decrypt($return_rsa,$pubkey);

        $pXML=$this->parseXML($return_xml);

        $decrypted=$pXML["body"];
        $rsacode= strip_tags($decrypted);
        $dejson=$this->desing3des($rsacode,$key);

        $result_json=strip_tags($dejson);
        $TestStr = preg_replace('/\s(?=)/', '', $result_json);
        $json_clean2=str_replace('&quot;','"',$TestStr);
        $result_data=json_decode($json_clean2,true);

        if($rsa_decode!=strtoupper(md5($return_xml)))
            return 2;

        if($result_data['respCode'] == "000000") {
            switch ($result_data['tradeStatus']) {
                case "101"://还未支付
                    return 1;
                    break;
                case "100"://支付成功
                    return 0;
                    break;
                default :
                    return 2;
            }
        }
        return 3;
    }

    //3des加密
    public function sign3des($signstr,$key,$xml='CLOB_DATA'){
        $f_string="<$xml>".$signstr."</$xml>";
        $data = openssl_encrypt($f_string,'des-ede3',$key,0);
        return base64_decode($data);
    }

    //3des解密
    public function desing3des($removetag,$key){
        $result= openssl_decrypt($removetag,'des-ede3',$key,0);
        return $result;
    }


    public function private_encrypt($data,$privateKey,$charset='base64') {
        $key = openssl_get_privatekey($privateKey);
        openssl_private_encrypt($data,$sign,$privateKey);

        if($charset == 'base64')
        {
            $sign = base64_encode($sign);
        }else if($charset == 'bin2hex')
        {
            $sign = bin2hex($sign);
        }

        openssl_free_key($key);
        return $sign;
    }

    public function public_decrypt($data,$pubkey){
        $key = openssl_get_publickey($pubkey);
        $hex2bin=hex2bin($data);
        openssl_public_decrypt($hex2bin,$decode_sign,$pubkey);
        openssl_free_key($key);
        return $decode_sign;
    }

    public function toXml($array,$xml_name)
    {
        $xml = "<$xml_name>";
        forEach($array as $k => $v){
            $xml.='<'.$k.'>'.$v.'</'.$k.'>';
        }
        $xml.= "</$xml_name>";
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
                if($node->children()){
                    $k = $node->getName();
                    $nodeXml = $node->asXML();
                    $v = substr($nodeXml, strlen($k)+2, strlen($nodeXml)-2*strlen($k)-5);

                }else{
                    $k = $node->getName();
                    $v = (string)$node;
                }

                if($encode!="" && $encode != "UTF-8"){
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

    public function checkBOM ($str) {
        $charset[1] = substr($str, 0, 1);
        $charset[2] = substr($str, 1, 1);
        $charset[3] = substr($str, 2, 1);
        if (ord($charset[1]) == 239 && ord($charset[2]) == 187 && ord($charset[3]) == 191) {
            $rest = substr($str, 3);
            $rest = checkBOM($rest);
            return $rest;
        }
        else return $str;
    }

    function is_json($string){
        json_decode($string);
        return (json_last_error()==JSON_ERROR_NONE);
    }

}