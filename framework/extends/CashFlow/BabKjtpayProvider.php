<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(FRAMEWORK_PATH . 'extends/Signature/KjtRsa.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabKjtpayProvider extends CashFlowProvider implements CashFlowProviderImp
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
        $data_all = array_merge($data,$pool);
        $this->send_api($data_all);
    }

    public function send_api($data)
    {
        $rsa = new KjtRsa($data['rsakey'], $data['key1']);
        $public_param = $this->getPublicParam($data, 'instant_trade');
        $biz_content = $this->getInstantTradeData($data);

        $public_param["biz_content"] = $rsa->encrypt(json_encode($biz_content));
        $public_param["sign"] = $rsa->sign($public_param);

        foreach($public_param as $key => $val) {
            $public_param[$key] = urlencode($val);
        }

        $jsapi = http_build_query($public_param);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '快捷通，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'], $jsapi, '快捷通', 'BabKjtpay');

        $output_array = json_decode(trim($output), true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '快捷通，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);


        if($output_array['code'] != 'S10000'){
            $output_array['msg'] = empty($output_array['msg']) ? '请求超时' : $output_array['msg'] . ' - ' . $output_array['sub_msg'];
            $error_treat = $this->error_treat('快捷通', 'BabKjtpay', $output, $data, $this->i, $output_array['msg']);
            if(!$error_treat['msg']) {
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        } else {
            $result = $output_array["biz_content"];
            $output_array["biz_content"] = json_encode($output_array["biz_content"], 320);
            $verifyResult = $rsa->verify($output_array, $output_array['sign']);
            if(!$verifyResult) {
                $this->error_treat('快捷通', 'BabKjtpay', $output, $data, 999, '验签未通过');
            }

            if($this->i != 0){
                $this->update_order_times($data);
            }

            $this->poolUpdataTransaction($data);

            $url = $result['return_url'];
            if($data['api_type'] == 1){
                $this->api_out($data, $url);
            }

            if ($data['api_type'] == 2 || $data['swift'] == "ALIPAY_APP") {
                return array('url' => $url);
            }

            $cash = [
                'ordernumber' => $data['pay_order_number'],
                'money' => $data['pay_amount'],
                'banktype' => $data['swift'],
                'url' => $url
            ];
            $this->shared->cash($cash);//轉址收銀台;
        }
    }

    public function getBankcode($swift){
        //WECHAT:微信 ALIPAY:支付宝 UPOP:银联
        switch ($swift){
            case "ALIPAY":
                return "ALIPAY";
                break;
            default:
                return "ALIPAY";
        }
    }

    public function getOrder($orderList = array())
    {
        $rsa = new KjtRsa($orderList['rsakey'], $orderList['key1']);
        $public_param = $this->getPublicParam($orderList, 'trade_query');
        $biz_content = [
            "out_trade_no" => $this->shared->concat_upstream_order_number($orderList['upstream_order_number'], $orderList['upstream_times']),
        ];

        $public_param["biz_content"] = $rsa->encrypt(json_encode($biz_content));
        $public_param["sign"] = $rsa->sign($public_param);

        foreach($public_param as $key => $val) {
            $public_param[$key] = urlencode($val);
        }

        $jsapi = http_build_query($public_param);
        $output = $this->shared->curl($orderList['query'], $jsapi, '快捷通', 'BabKjtpay');

        $output_array = json_decode($output, true);

        if($output_array['code'] != 'S10000') {
            return 3;
        }

        // 验签
        $result = $output_array["biz_content"];
        $output_array["biz_content"] = json_encode($output_array["biz_content"], 320);
        $verifyResult = $rsa->verify($output_array, $output_array['sign']);
        if(!$verifyResult) {
            return 2;
        }

        // 支付成功判斷
        return (in_array($result['status'], ['TRADE_FINISHED', 'TRADE_SUCCESS'])) ? 0 : 1;
    }

    /**
     * @param $service string
     *      支付: instant_trade
     *
     */
    private function getPublicParam($data, $service)
    {
        return [
            "request_no" => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
            "service" => $service,
            "version" => '1.0',
            "partner_id" => $data['t_account'],
            "charset" => 'UTF-8',
            "sign_type" => 'RSA',
            "sign" => '',
            "timestamp" => $data['order_time'],
            "format" => 'JSON',
            "biz_content" => '',
        ];
    }


    /**
     * 產生支付資料
     *
     */
    private function getInstantTradeData($data)
    {
        $amount = sprintf("%.2f", $data['pay_amount']);
        $target_organization = $this->getBankcode($data['swift']);
        // $ip = $data['user_ip'];
        $ip = '35.194.228.95';
        return [
            "payer_identity" => 'anonymous',
            "payer_identity_type" => '1',
            "payer_platform_type" => '1',
            "payer_ip" => $ip,
            "biz_product_code" => '20701', // 业务产品码，20601-即时到帐-电商，20401-即时到帐-互金，20602-线下收单（支持T0退款)，20701-收单（先分账后结算）
            "cashier_type" => 'API', // WEB, H5, SDK, API
            "trade_info" => [
                [
                    'out_trade_no' => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
                    'subject' => $data['stores'] . ",业务交易号:" . $data["my_order_number"],
                    'currency' => 'CNY',
                    'price' => $amount,
                    'quantity' => '1',
                    'total_amount' => $amount,
                    'payee_identity' => $data['t_mid'],
                    'notify_url' => $data['turn'],
                ]
            ],
            "pay_method" => [
                "pay_product_code" => '64',
                "amount" => $amount,
                "target_organization" => $target_organization,
            ],
            "terminal_info" => [
                'terminal_type' => '00',
                'ip' => $ip,
            ]
        ];
    }

}