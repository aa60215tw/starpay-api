<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabKailientungTenProvider extends CashFlowProvider implements CashFlowProviderImp
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
        $jsapi =
            [
                'inputCharset' => '1',
                'receiveUrl' => $data['turn'],
                'version' => 'v1.0',
                'language' => '1',
                'signType' => '0',
                'merchantId' => $data['t_mid'],
                'orderNo' => $data['upstream_order_number'],
                'orderAmount' =>  $data['pay_amount']*100,
                'orderCurrency' => '156',
                'orderDatetime' => date("YmdHis",strtotime($data['order_time'])),
                'productName' => $data['my_order_number'],
                'payType' => '99',
                'key' => $data['key']
            ];

        $sign_data = urldecode(http_build_query($jsapi));

        $sign = strtoupper(md5($sign_data));
        $jsapi['signMsg'] = $sign;
        unset($jsapi['key']);
//        $jsapi = http_build_query($jsapi);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '开联通，送往上游字段:'.$jsapi;
        $path_our_log->warn($msg);

        $this->poolUpdataTransaction($data);
        $url = $data['address'];
        echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.ShowForm.submit();\">";
        echo "<form name=\"ShowForm\" method=\"post\" action=\"http://happytopay.jishenghe168.com/ServerFrom.php\" target=\"_self\">";
        foreach ($jsapi as $k => $v){
            echo "<input type=\"hidden\" name=\"".$k."\" value=\"".$v."\"/>";
        }
        echo "<input type=\"hidden\" name=\"url\" value=\"".$url."\"/>";
        echo "</form></body></html>";
        exit;


    }

    public function getBankcode($swift){
    }

    public function getOrder($orderList = array())
    {
        $jsapi =
            [
                'merchantId' => $orderList['t_mid'],
                'version' => 'v1.5',
                'signType' => '0',
                'orderNo' => $orderList['upstream_order_number'],
                'orderDatetime' =>  date("YmdHis",strtotime($orderList['order_time'])),
                'queryDatetime' => date("YmdHis"),
                'key' => $orderList["key"]
            ];


        $sign_data = urldecode(http_build_query($jsapi));

        $sign = strtoupper(md5($sign_data));
        $jsapi['signMsg'] = $sign;
        unset($jsapi['key']);
        $jsapi = http_build_query($jsapi);

        $output = $this->shared->curl($orderList['query'], $jsapi,'开联通','BabKailientung');
        parse_str($output, $output_array);

        if(!empty($output_array['respCode']) && $output_array['respCode'] != '10027')
            return 3;

        if($output_array['respCode'] == '10027')
            return 1;

        $param = array(
            'merchantId' => $output_array['merchantId'],
            'version' => $output_array['version'],
            'language' => $output_array['language'],
            'signType' => $output_array['signType'],
            'payType' => $output_array['payType'],
            'issuerId' => $output_array['issuerId'],
            'mchtOrderId' => $output_array['mchtOrderId'],
            'orderNo' => $output_array['orderNo'],
            'orderDatetime' => $output_array['orderDatetime'],
            'orderAmount' =>  $output_array['orderAmount'],
            'payDatetime' => $output_array['payDatetime'],
            'ext1' => $output_array['ext1'],
            'ext2' => $output_array['ext2'],
            'payResult' => $output_array['payResult'],
        );

        $signstr = '';
        foreach($param as $k => $v) {
            if($v != '' || $k == 'signMsg') {
                $signstr .= $k . "=" . $v . "&";
            }
        }
        $signstr .= 'key='. $orderList['key'];
        $sign = strtoupper(md5($signstr));

        if ($sign != $output_array['signMsg'])
            return 2;

        if($output_array['payResult'] != '1')
            return 1;

        return 0;
    }
}

?>