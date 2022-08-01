<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');
require_once(FRAMEWORK_PATH . 'collections/NhnaProvinceCodeCollection.php');

class BabCarhandTestProvider extends CashFlowProvider implements CashFlowProviderImp
{
    protected $md5_class;
    protected $pool_judgment;
    protected $waitTime;

    public function __construct()
    {
        parent::__construct();
        $this->md5_class = new Md5();
        $this->pool_judgment = 'all';
    }

    public function send($data)
    {
        $data["upstream_order_number"] = date("YmdHis");
        $pool = $this->poolAddOrder($data, $this->pool_judgment, true, 0, false, false);
        if (!$pool) {
            return false;
        }
        $data_all = array_merge($data, $pool);
        $this->send_api($data_all);
    }

    public function send_api($data)
    {
        $curlTimeOut = 7;
        $jsapi_array = [
            "merchantid" => $data['t_mid'],
            "cusordernum" => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
            "amount" => ($data['pay_amount'] * 100) / 100,
            "paytype" => $this->getBankcode($data['swift']),
            "notifyurl" => $data['turn'],
        ];

        $jsapi_array['sign'] = $this->md5_class->md5Sign($data['key'], $jsapi_array);
        $jsapi_array['service'] = 'Order_Order.Create';
        $jsapi_array['type'] = '1';

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '抢单test，送往上游字段:' . json_encode($jsapi_array, 320);
        $path_our_log->warn($msg);
        $member_ip = $this->shared->get_ip();
        $header = array(
            "CLIENT-IP:$member_ip",
            "X-FORWARDED-FOR:$member_ip",
        );
        $output = $this->shared->curl($data['address'], $jsapi_array, '抢单test', 'BabCarhand', 'post', $header, 'off', $curlTimeOut);
        $output = trim($output);
        $output_array = json_decode($output, true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '抢单test，上游订单号:' . $data['upstream_order_number'] . '\r\n上游返回字段:' . $output;
        $path_up_log->warn($msg);

        $data['upstream_parameter'] = "入參:" . json_encode($jsapi_array, 320) . " 出參:$output";

        if ($output_array['data']['code'] != '0') {
            $output_array['err_msg'] = empty($output_array['data']['msg']) ? '请求超时' : $output_array['data']['msg'];
            $error_treat = $this->error_treat('抢单test', 'BabCarhand', $output, $data, 10, $output_array['err_msg']);
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

            $url = $output_array['data']['qrcode'];
            $data['pay_amount'] = $output_array['data']['amount'];
            if ($data['api_type'] == 1) {
                $this->api_out($data, $url);
            }
            if ($data['api_type'] == 2) {
                return array('url' => $url);
            }
            if ($data['swift'] == "ALIPAY_APP" || $data['swift'] == "WXPAY_APP") {
                header("location:$url");
                exit();
            }

            $cash =
                [
                    'ordernumber' => $data['pay_order_number'],
                    'money' =>$data['pay_amount'],
                    'banktype' => $data['swift'],
                    'url' => $url
                ];
            $this->shared->cash($cash);//轉址收銀台;
        }

        return '';
    }

    public function getBankcode($swift)
    {
        switch ($swift) {
            case "ALIPAY":
                return "alipay";
            case "ALIPAY_APP":
                return "alipay";
            case "WXPAY":
                return "wechat";
            case "WXPAY_APP":
                return "wechat";
            default:
                return "alipay";
        }
    }

    public function getOrder($orderList = array())
    {
        $curlTimeOut = 15;
        $jsapi = [
            "merchantid" => $orderList['t_mid'],
            "cusordernum" => $this->shared->concat_upstream_order_number($orderList['upstream_order_number'], $orderList['upstream_times']),
        ];
        $jsapi['sign'] = $this->md5_class->md5sign($orderList['key'], $jsapi);
        $jsapi['service'] = 'Order_Order.queryOrder';

        $output = $this->shared->curl($orderList['query'], $jsapi, '抢单test', 'BabCarhand', 'post', [], 'off', $curlTimeOut);
        $output_array = json_decode($output, true);

        if ($output_array['data']['code'] != '0') {
            return 3;
        }

        $output_sign = $output_array['data']["sign"];
        unset($output_array['data']["sign"]);

        $sign = strtoupper($this->md5_class->md5sign($orderList['key'], $output_array['data']));

        if ($sign != $output_sign) {
            return 2;
        }

        if ($output_array['data']['orstatus'] != '1')
            return 1;

        return 0;
    }


}