<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabKtProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $pool_judgment = 'all';
        $upstream_order_number = $this->shared->new_upstream_order_number($data['user_id']);
        $data["upstream_order_number"] = $upstream_order_number;
        $pool = $this->poolAddOrder($data, $pool_judgment);
        if (!$pool) {
            return false;
        }
        $data_all = array_merge($data, $pool);
        $this->send_api($data_all);
    }

    public function send_api($data)
    {
        $MD5 = new Md5();
        $upstream_order_number = $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']);
        $t_mid = $data["t_mid"];
        $pay_amount = $data["pay_amount"];
        $jsapi = array(
            'requestNo' => $upstream_order_number,
            'protocol' => 'HTTP_FORM_JSON',
            'service' => 'b2b.trade.fastpay.cashier',
            'version' => '1.0',
            'partnerId'=> $data["t_account"],
            'signType' => 'MD5',
            'merchOrderNo' => $upstream_order_number,
            'notifyUrl' => $data['turn'],
            'buyerOutUserID' => $data["t_mid"],
            'fastPaymentOrders' => "[{\"subMerchOrderNo\":\"$upstream_order_number\",\"sellerOutUserID\":\"$t_mid\",\"tradeName\":\"$upstream_order_number\",\"tradeAmount\":\"$pay_amount\"}]",
        );

        $jsapi['sign'] = $MD5->md5sign($data['key'], $jsapi, 'on', 'off', '', true);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '笨熊，送往上游字段:' . json_encode($jsapi, 320);
        $path_our_log->warn($msg);

        $this->poolUpdataTransaction($data);
        $formItemString='';
        foreach($jsapi as $key => $value){
            $formItemString.="<input name='{$key}' type='hidden' value='{$value}'/>";
        }
        ob_clean();
        echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.ShowForm.submit();\">";
        echo "<form name=\"ShowForm\" method=\"post\" action=\"".$data['address']."\" target=\"_self\">";
        echo $formItemString;
        echo "</form></body></html>";
        ob_flush();
        exit;

    }

    public function getBankcode($swift)
    {
        switch ($swift) {
            case "1100":
                return "ICBC";
            case "1101":
                return "ABC";
            case "1102":
                return "CMB";
            case "1103":
                return "CIB";
            case "1104":
                return "CITIC";
            case "1107":
                return "BOC";
            case "1112":
                return "CEB";
            case "1116":
                return "BOS";
            case "1121":
                return "PAB";
        }
    }

    public function getOrder($orderList = array())
    {
        $MD5 = new Md5();
        $jsapi = array(
            "merchantCode" => $orderList["t_mid"],
            "outOid" => $this->shared->concat_upstream_order_number($orderList['upstream_order_number'], $orderList['upstream_times']),
            'platformOid' => '',
            "payAmount" => $orderList['pay_amount'] * 100,
            "orderDate" => date("Y-m-d", strtotime(str_replace('-', '', $orderList['order_time']))),
        );

        $jsapi['sign'] = $MD5->md5sign($orderList['key'], $jsapi, 'on', 'on');
        $geturl = $orderList['query'];
        $output = $this->shared->curl($geturl, http_build_query($jsapi), '笨熊', 'BabLqpay');
        $output = trim($output);
        $output_array = json_decode($output, true);

        if ($output_array['code'] != '000000')
            return 3;

        $output_sign = $output_array['sign'];
        unset($output_array['msg'], $output_array['code']);
        $sign = $MD5->md5sign($orderList['key'], $output_array['value'], 'on', 'on');

        if ($sign != $output_sign)
            return 2;

        if ($output_array['value']['payStatus'] != '2')
            return 1;

        return 0;
    }

}

?>