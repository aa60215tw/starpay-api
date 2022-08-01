<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabIpsProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $pool_judgment = 'all';
        $upstream_order_number = $this->shared->new_upstream_order_number($data['user_id']);
        $data["upstream_order_number"] = $upstream_order_number;
        $pool = $this->poolAddOrder($data,$pool_judgment , false);
        if (!$pool) {
            return false;
        }

        $data_all = array_merge($data,$pool);
        $data_all["provider"] = "BabIps";
//        $newData = array("my_order_number" => $data_all['my_order_number'], "pay_amount" => $data_all['pay_amount']
//                            , "turn" =>$data_all['turn'] , "address" => $data_all['address'] , "provider" => "BabIps" , "obtp_code" => $data_all['obtp_code']);
        ob_clean();
        echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.ShowForm.submit();\">";
        echo "<form name=\"ShowForm\" method=\"post\" action=\"".OBTP_URL."\" target=\"_self\">";
        echo "<input type=\"hidden\" name=\"data\" value=\"".base64_encode(json_encode($data_all,320))."\"/>";
        echo "</form></body></html>";
        ob_end_flush();
        exit();
    }

    public function send_api($data)
    {
        //$poolCollection = new PoolCollection();
        $orderList = $this->addOrder($data);
        if (!$orderList) {
            return false;
        }
        //$poolList = $poolCollection->getRecord(array("id" => $orderList['pool_id']));
        $data = array_merge($orderList ,$data);

        $url = $data['address'];
        $t=time();
        $notifyUrl = $data['turn'];
        $t_account= $data['t_account'];

        $body =
            [
                'MerBillNo' => $data['upstream_order_number'],
                'Amount' => $data['pay_amount'],
                'Date' => date("Ymd",$t),
                'CurrencyType' => '156',
                'GatewayType' => '01',
                'Lang' => 'GB',
                'Merchanturl' => "<![CDATA[$notifyUrl]]>",
                'FailUrl' => "<![CDATA[$notifyUrl]]>",
                'ServerUrl' => "<![CDATA[$notifyUrl]]>",
                'OrderEncodeType' => '5',
                'RetEncodeType' => '17',
                'RetType' => '1',
                'BillEXP' => '2',
                'GoodsName' => $data['my_order_number'],
                'IsCredit' => '1',
                'BankCode' => $this->getBankcode($data['bank_name']),
                'ProductType' => '1',
            ];

        $body_xml = $this->toXml($body,"body");
        $Signature=md5($body_xml.$t_account.$data['key']);

        $head =
            [
                'Version' => 'v1.0.0',
                'MerCode' => $t_account,
                'MerName' => '',
                'Account' => $data['t_mid'],
                'MsgId' => '1',
                'ReqDate' => date("YmdHis"),
                'Signature' => $Signature,
            ];

        $head_xml = $this->toXml($head,"head");
        $pGateWayReq = array('GateWayReq' => $head_xml.$body_xml);
        $pGateWayReq_xml = $this->toXml($pGateWayReq,"Ips");

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '环迅，送往上游字段:'.$pGateWayReq_xml;
        $path_our_log->warn($msg);
        $this->poolUpdataTransaction($data);

        echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.ShowForm.submit();\">";
        echo "<form name=\"ShowForm\" method=\"post\" action=\"http://happytopay.kevloo.com/ServerFrom.php\" target=\"_self\">";
        echo "<input type=\"hidden\" name=\"pGateWayReq\" value=\"".$pGateWayReq_xml."\"/>";
        echo "<input type=\"hidden\" name=\"url\" value=\"".$url."\"/>";
        echo "</form></body></html>";
        exit;
    }

    public function getBankcode($swift)
    {
        return $swift;
    }

    public function getOrder($orderList = array())
    {
        $body =
            [
                'MerBillNo' => $orderList['upstream_order_number'],
                'Date' => date("Ymd",strtotime(str_replace('-', '', $orderList['order_time']))),
                'Amount' => number_format($orderList['pay_amount'], 2),
            ];

        $body_xml = $this->toXml($body,"body");
        $Signature=md5($body_xml.$orderList['t_account'].$orderList['key']);

        $head =
            [
                'Version' => 'v1.0.0',
                'MerCode' => $orderList['t_account'],
                'MerName' => '',
                'Account' => $orderList['t_mid'],
                'ReqDate' => date("YmdHis"),
                'Signature' => $Signature,
            ];

        $head_xml = $this->toXml($head,"head");
        $OrderQueryReq = array('OrderQueryReq' => $head_xml.$body_xml);
        $OrderQueryReq_xml = $this->toXml($OrderQueryReq,"Ips");
        $client = new SoapClient($orderList['query']);
        $output = $client->getOrderByMerBillNo($OrderQueryReq_xml);

        $output_array = $this->parseXML($output);
        $output_array = $output_array['OrderQueryRsp'];
        $output_array = "<OrderQueryRsp>$output_array</OrderQueryRsp>";
        $output_array = $this->parseXML($output_array);
        $output_head_xml = $output_array['head'];
        $output_head_xml = "<head>$output_head_xml</head>";
        $output_head = $this->parseXML($output_head_xml);
        $output_body_xml = $output_array['body'];
        $output_body_xml = "<body>$output_body_xml</body>";
        $output_body = $this->parseXML($output_body_xml);

        if($output_head['RspCode'] != '000000')
            return 1;

        $Signature = md5($output_body_xml.$orderList['t_account'].$orderList['key']);
        if ($Signature != $output_head['Signature'])
            return 2;

        if($output_body['Status'] != 'Y')
            return 1;

        return 0;
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

    public function getXmlEncode($xml)
    {
        $ret = preg_match ("/<?xml[^>]* encoding=\"(.*)\"[^>]* ?>/i", $xml, $arr);
        if($ret) {
            return strtoupper ( $arr[1] );
        } else {
            return "";
        }
    }
}

?>