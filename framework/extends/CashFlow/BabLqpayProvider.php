<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabLqpayProvider extends CashFlowProvider implements CashFlowProviderImp
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

        $jsapi = array(
            "outOid" => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
            "merchantCode" => $data["t_mid"],
            "payType" => '60',
            "payAmount" => $data["pay_amount"] * 100,
            'bankCode' => '1101',
            "goodName" => 'goodName'
        );
        $jsapi['sign'] = $MD5->md5sign($data['key'], $jsapi, 'on', 'on');
        $jsapi['notifyUrl'] = $data['turn'];

        $geturl = $data['address'] . "?" . http_build_query($jsapi);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = 'LQ复联，送往上游字段:' . json_encode($jsapi, 320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($geturl, $jsapi, 'LQ复联', 'BabLqpay', 'post');
        $output = trim($output);
        $output_array = json_decode($output, true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = 'LQ复联，上游返回字段:' . $output . "\r\n" . '上游返回字段JSON:' . $output;
        $path_up_log->warn($msg);

        if ($output_array['code'] != '000000') {
            $output_array['msg'] = empty($output_array['msg']) ? '请求超时' : $output_array['msg'];
            $error_treat = $this->error_treat('LQ复联', 'BabLqpay', $output, $data, $this->i, $output_array['msg']);
            if (!$error_treat['msg']) {
                $this->i++;
                $data = $error_treat['data'];
                return $this->send_api($data);
            }
        } else {
            if ($this->i != 1) {
                $this->update_order_times($data);
            }

            $this->poolUpdataTransaction($data);
            $url = $output_array['value']['payUrl'];

            header("location:$url");
            exit();

        }

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
        $output = $this->shared->curl($geturl, http_build_query($jsapi), 'LQ复联', 'BabLqpay');
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