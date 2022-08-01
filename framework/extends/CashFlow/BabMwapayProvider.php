<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabMwapayProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $des = new Des();
        $pool_judgment = 'all';
        $upstream_order_number = $this->shared->new_upstream_order_number($data['user_id']);
        $data["upstream_order_number"] = $upstream_order_number;
        $pool = $this->poolAddOrder($data,$pool_judgment , false);
        if (!$pool) {
            return false;
        }

        $data_all = array_merge($pool,$data);
        $data_all["provider"] = "BabMwapay";

        ob_clean();
        echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.ShowForm.submit();\">";
        echo "<form name=\"ShowForm\" method=\"post\" action=\"".TENPAY_URL."\" target=\"_self\">";
        echo "<input type=\"hidden\" name=\"data\" value=\"".base64_encode($des->sign3des(AUTH_KEY,json_encode($data_all,320)))."\"/>";
        echo "</form></body></html>";
        ob_end_flush();
        exit();
    }

    public function send_api($data,$type=true)
    {
        if($type){
            $orderList = $this->addOrder($data);
            if (!$orderList) {
                return false;
            }
            $data = array_merge($orderList ,$data);
        }
        $MD5 = new Md5();
        $jsapi=array(
            'spid' => $data["t_mid"],
            'sp_userid' => $data["t_mid"],
            "spbillno" => $data["upstream_order_number"],
            'money' => $data["pay_amount"]*100,
            'cur_type'=>'1',
            'user_type'=>'1',
            'channel'=>'1',
            'return_url' => $data['turn'],
            'notify_url' => $data['turn'],
            'memo' => $data["my_order_number"],
            'encode_type' => 'MD5',
            'bank_accno' => $data['bank_name'],
            'bank_acctype' => '01'
        );

        $jsapi['sign'] = $MD5->md5sign($data['key'],$jsapi,'on','off');

        $geturl=$data['address']."?".http_build_query($jsapi);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '天付宝银联，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($geturl, $jsapi,'天付宝银联','BabMwapay','get');
        $output = trim($output);
        $output = mb_convert_encoding($output,'UTF-8','GBK');
        preg_match_all("/\<root\>(.*?)\<\/root\>/s",$output,$root);
        $output_array = $this->parseXML($root[0][0]);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '天付宝银联，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);

        if($output_array['retcode'] != '00'){
            $output_array['msg'] = empty($output_array['retmsg'])?'请求超时':$output_array['retmsg'];
            $error_treat = $this->error_treat('天付宝银联','Mwappay',$output,$data,$this->i,$output_array['msg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data,false);
            }
        } else{
            if($this->i != 0){
                $this->update_order_number($data);
            }
            
            $this->poolUpdataTransaction($data);
            
            exit($output);

        }

    }

    public function getBankcode($swift){
    }

    public function getOrder($orderList = array())
    {
        $MD5 = new Md5();
        $jsapi=array(
            'spid' => $orderList["t_mid"],
            "spbillno" => $orderList["upstream_order_number"],
            'channel'=>'1',
            'encode_type' => 'MD5',
        );

        $jsapi['sign'] = $MD5->md5sign($orderList['key'],$jsapi,'on','off');
        $geturl=$orderList['query']."?".http_build_query($jsapi);

        $output = $this->shared->curl($geturl, $jsapi,'天付宝银联','BabMwapay','get');

        $output_array = $this->parseXML($output);
        $output_array = $this->array_iconv($output_array);

        if($output_array['retcode'] != '00')
            return 3;

        $output_sign = $output_array['sign'];
        unset($output_array['retmsg'],$output_array['retcode']);
        $sign = $MD5->md5sign($orderList['key'],$output_array,'on','off');

        if ($sign != $output_sign)
            return 2;

        if($output_array['result'] != '1')
            return 1;

        return 0;
    }

    public function parseXML($xmlSrc)
    {
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

    public function getXmlEncode($xml)
    {
        $ret = preg_match ("/<?xml[^>]* encoding=\"(.*)\"[^>]* ?>/i", $xml, $arr);
        if($ret) {
            return strtoupper ( $arr[1] );
        } else {
            return "";
        }
    }

    public function array_iconv($arr, $in_charset="gbk", $out_charset="utf-8")
    {
        $ret = eval('return '.iconv($in_charset,$out_charset,var_export($arr,true).';'));
        return $ret;
    }
}

?>