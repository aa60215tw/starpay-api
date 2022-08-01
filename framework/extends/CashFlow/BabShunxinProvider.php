<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabShunxinProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $pool_judgment = 'all';
        $data["upstream_order_number"] = date("YmdHis");
        $pool = $this->poolAddOrder($data, $pool_judgment);
        if (!$pool) {
            return false;
        }

        $data_all = array_merge($data, $pool);
        $this->send_api($data_all);
    }
    public function send_api($data)
    {
        $jsapi = [
            "uid" => $data['t_mid'],
            "price" => sprintf ("%.2f", $data['pay_amount']),
            "pay_way" => '10',
            "notify_url" => $data['turn'],
            "return_url" => "http://35.194.249.119/notify.php",
            "order_id" => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
            "order_uid" => $data['t_mid'],
            "goods_name" => $data["my_order_number"]. "-" . $data["upstream_times"],
            //"bank_card_no" => $data['bank_name'],
            "token" => $data['key']
        ];

        ksort($jsapi);
        $jsapiString = urldecode(http_build_query($jsapi));
        $sign = md5($jsapiString);
        $jsapi['key'] = $sign;
        unset($jsapi['token']);
        $jsapi = http_build_query($jsapi);
        // log
        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '顺心支付，送往上游字段:'.$jsapi;
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'], $jsapi, '顺心支付', 'BabShunxin');
        $output = trim($output);
        $output_array = json_decode($output,true);
        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '顺心支付，上游订单号:' . $data['upstream_order_number'] . '\r\n上游返回字段:' . $output;
        $path_up_log->warn($msg);

        if($output_array['code'] != '0') {
            $output_array['msg'] = empty($output_array['msg'])?'请求超时':$output_array['msg'];
            $error_treat = $this->error_treat('顺心支付','BabShunxin', $output, $data, $this->i, $output_array['msg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                return $this->send_api($data);
            }
        } else{
            if($this->i != 1){
                $this->update_order_times($data);
            }
            $this->poolUpdataTransaction($data);
            $url = $output_array['data']['pay_url'];
            echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.ShowForm.submit();\">";
            echo "<form name=\"ShowForm\" method=\"post\" action=\"$url\" target=\"_self\">";
            echo "</form></body></html>";
            exit;
        }
    }

    public function getBankcode($swift){

    }

    public function getOrder($orderList = array())
    {
        $signKey = $orderList['key'];

        $jsapi = array(
            "uid" => $orderList['t_mid'],
            "r" => time().$orderList['upstream_order_number'],
            "order_id" => $this->shared->concat_upstream_order_number($orderList['upstream_order_number'], $orderList['upstream_times']),
            "token" => $signKey
        );

        ksort($jsapi);
        $jsapiString = urldecode(http_build_query($jsapi));
        $sign = md5($jsapiString);
        $jsapi['key'] = $sign;
        unset($jsapi['token']);
        $jsapi = http_build_query($jsapi);

        $output = $this->shared->curl($orderList['query'], $jsapi, '顺心支付', 'BabNhna');
        $output_array = json_decode($output,true);

        if ($output_array['code'] != '0') {
            return 3;
        }

        if($output_array['data']['status'] != '2')
            return 1;

        return 0;
    }
}