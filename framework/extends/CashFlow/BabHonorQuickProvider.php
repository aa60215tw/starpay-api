<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabHonorQuickProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $pool_judgment = 'all';
        $data["upstream_order_number"] = $data["upstream_order_number"] = date("YmdHis");
        $pool = $this->poolAddOrder($data,$pool_judgment);
        if (!$pool) {
            return false;
        }

        $data_all = array_merge($data,$pool);
        $this->send_api($data_all);
    }

    public function send_api($data)
    {
        $jsapi = [
            'banktype' => $this->getBankcode($data['swift']),
            'partner' => $data['t_mid'],
            'paymoney' => $data['pay_amount'],
            'ordernumber' => $data['upstream_order_number'],
            'callbackurl' => $data['turn'],
        ];

        $signSource = sprintf(
            "partner=%s&banktype=%s&paymoney=%s&ordernumber=%s&callbackurl=%s%s",
            $jsapi['partner'],
            $jsapi['banktype'],
            $jsapi['paymoney'],
            $jsapi['ordernumber'],
            $jsapi['callbackurl'],
            $data['key']
        );
        $jsapi['sign'] = md5($signSource);
        $jsapi = http_build_query($jsapi);

        // log
        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '荣耀快捷支付，送往上游字段:'.$jsapi;
        $path_our_log->warn($msg);

        $url = sprintf('%s?%s', $data['address'], $jsapi);
        $output = $this->shared->curl($url, '', '荣耀快捷支付', 'BabHonor', 'get');
        $output = trim($output);
        // TODO: detect error msg in html body
        $this->poolUpdataTransaction($data);
        exit($output);
    }

    public function getBankcode($swift){
        switch ($swift){
            case "TENPAY":
                return "QUICKPAY"; // 工商银行
            default:
                return "QUICKPAY";
        }
    }

    public function getOrder($orderList = array())
    {
        return 3;
    }
}