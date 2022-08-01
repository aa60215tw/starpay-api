<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabKailientungProvider extends CashFlowProvider implements CashFlowProviderImp
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

        $data_all = array_merge($pool, $data);
        $data_all["provider"] = "BabKailientung";

        ob_clean();
        echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.ShowForm.submit();\">";
        echo "<form name=\"ShowForm\" method=\"post\" action=\"".OBTP_URL."\" target=\"_self\">";
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
                'payType' => '1',
                'issuerId' => $this->getBankcode($data['bank_name']),
                'key' => $data['key']
            ];

        $sign_data = urldecode(http_build_query($jsapi));

        $sign = strtoupper(md5($sign_data));
        $jsapi['signMsg'] = $sign;
        unset($jsapi['key']);
        $jsapi = http_build_query($jsapi);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '开联通，送往上游字段:'.$jsapi;
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'], $jsapi,'开联通','BabKailientung');
        $output = trim($output);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '开联通，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output,320);
        $path_up_log->warn($msg);

        $match = strpos($output,"错误代码");
        if($match != false || empty($output)) {
            preg_match_all("/\<p\>(.*?)\<\/p\>/s",$output,$msg);
            $output_array['msg'] = strip_tags($msg[0][0]);
            $output_array['msg'] = empty($output_array['msg'])?'请求超时':$output_array['msg'];
            $error_treat = $this->error_treat('开联通','BabKailientung',$output,$data,$this->i,$output_array['msg']);
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
        switch ($swift){
            case "1108":
                return "comm";
            case "1106":
                return "ccb";
            case "1102":
                return "cmb";
            case "1110":
                return "cmbc";
            case "1101":
                return "abc";
            case "1100":
                return "icbc";
            case "1107":
                return "boc";
            case "1109":
                return "spdb";
            case "1112":
                return "ceb";
            case "1119":
                return "psbc";
            case "1104":
                return "citic";
            case "1111":
                return "hxb";
            case "1103":
                return "cib";
            case "1121":
                return "pingan";
            case "1113":
                return "bob";
            case "1116":
                return "bos";
            case "1114":
                return "cgb";
            default:
                return "icbc";
        }
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