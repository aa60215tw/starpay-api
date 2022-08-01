<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');
require_once(FRAMEWORK_PATH . 'collections/NhnaProvinceCodeCollection.php');

class BabNhnaProvider extends CashFlowProvider implements CashFlowProviderImp
{
    protected $md5_class;
    protected $pool_judgment;
    protected $waitTime;

    public function __construct()
    {
        parent::__construct();
        $this->md5_class = new Md5();
        $this->pool_judgment = '';
        $this->waitTime = rand(1200, 1800);
    }

    public function send($data)
    {
        $data["upstream_order_number"] = date("YmdHis");

        $system = new SystemCollection();
        $switch = $system->getRecordsByCondition('`key` in (:key,:key1)', array('key' => "open_hnapay", 'key1' => "hnapay_ratio"), 1, 2);
        $switch_value = $switch['records'][0]['value'];
        $switch_ratio = $switch['records'][1]['value'];

        if ($switch_value) {
            $path_rand = rand(1, 100);
            if ($path_rand <= $switch_ratio && $data['swift'] == "ALIPAY" && ($data['user_id'] == "bab00072" || $data['user_id'] == "bab00027" || $data['user_id'] == "bab00079")) {
                $data['swift'] = "ALIPAY";
            } else {
                $data['swift'] = "ALIPAY_APP";
            }
        } else {
            $data['swift'] = "ALIPAY_APP";
        }

        if ($data['user_id'] == "bab00053") {
            if ($data['swift'] == "ALIPAY_APP") {
                $pool = $this->poolAddOrder($data, $this->pool_judgment, true, $this->waitTime, true, false);
            } else {
                $pool = $this->poolAddOrder($data, $this->pool_judgment, true, $this->waitTime, false, false);
            }
        } elseif ($data['user_id'] == "bab00072") {
            if ($data['swift'] == "ALIPAY_APP") {
                $pool = $this->poolAddOrder($data, $this->pool_judgment, true, $this->waitTime, true, false, 0, false);
            } else {
                $pool = $this->poolAddOrder($data, $this->pool_judgment, true, $this->waitTime, false, false, 0, false);
            }
        } elseif ($data['user_id'] == "bab00079") {
            if ($data['swift'] == "ALIPAY_APP") {
                $pool = $this->poolAddOrder($data, $this->pool_judgment, true, 144000, true, false, 0, false);
            } else {
                $pool = $this->poolAddOrder($data, $this->pool_judgment, true, 144000, false, false, 0, false);
            }
        } else {
            if ($data['swift'] == "ALIPAY_APP") {
                $pool = $this->poolAddOrder($data, $this->pool_judgment, true, 86500, true, false, 0, false);
            } else {
                $pool = $this->poolAddOrder($data, $this->pool_judgment, true, 86500, false, false, 0, false);
            }
        }

        if (!$pool) {
            return false;
        }
        $data_all = array_merge($data, $pool);
        $this->send_api($data_all);
    }

    public function send_api($data)
    {
        $curlTimeOut = 7;
        if ($data['swift'] == "ALIPAY_APP" && empty($data['alipay_user_id'])) {
            $data['swift'] = 'ALIPAY';
        }

        $jsapi_arrray = [
            "mch_no" => $data['t_account'],
            "sub_mch_no" => $data['t_mid'],
            "out_trade_no" => $this->shared->concat_upstream_order_number($data['upstream_order_number'], $data['upstream_times']),
            "body" => mb_substr($data['stores'], 0, 5, "utf-8") . ",业务交易号:" . $data["my_order_number"] . "-" . $data["upstream_times"],
            "fee_type" => 'CNY',
            "total_fee" => intval($data['pay_amount'] * 100),
            "trade_type" => $this->getBankcode($data['swift']),
            "expire_time" => date('Y-m-d H:i:s', strtotime("+5 minute")),
            "area_id" => $this->provinceCode($data['area1'], $data['area2']),
            "notify_url" => $data['turn'],
        ];

        if ($data['swift'] == "ALIPAY_APP") {
            $jsapi_arrray['buyerid'] = $data['alipay_user_id'];
        }

        $jsapi_arrray['sign'] = $this->md5_class->md5Sign($data['key'], $jsapi_arrray);
        $jsapi = json_encode($jsapi_arrray, 320);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '新生，送往上游字段:' . json_encode($jsapi, 320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'], $jsapi, '新生', 'BabNhna', 'post', ["Content-type: application/json;charset='utf-8'"], 'off', $curlTimeOut);
        $output = trim($output);

        $output_array = json_decode($output, true);
        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '新生，上游订单号:' . $data['upstream_order_number'] . '\r\n上游返回字段:' . $output;
        $path_up_log->warn($msg);

        $data['upstream_parameter'] = "入參:$jsapi 出參:$output";


        if ($output_array['result_code'] != 'success') {
            $output_array['err_msg'] = empty($output_array['err_msg']) ? '请求超时' : $output_array['err_msg'];
            if ($output_array['err_msg'] == "扫码支付异常" || $output_array['err_msg'] == "交易失败" || $output_array['err_msg'] == "内部系统异常" || $output_array['err_msg'] == '子商户状态异常') {
                if ($data['user_id'] == "bab00053") {
                    $data = $this->payOrderPoolChange($data, $this->pool_judgment, rand(480, 600), 4);
                } else {
                    $data = $this->payOrderPoolChange($data, $this->pool_judgment, $this->waitTime);
                }
            }
            $error_treat = $this->error_treat('新生', 'BabNhna', $output, $data, $this->i, $output_array['err_msg']);
            if (!$error_treat['msg']) {
                $this->i++;
                $data = $error_treat['data'];
                return $this->send_api($data);
            }
        } else {
            if ($this->i != 1) {
                $this->update_order_times($data);
            }

            // 水池状态 +1

            if ($data['user_id'] != "bab00053") {
                $this->poolUpdataTransaction($data, 1);
            } else {
                $this->poolUpdataTransaction($data);
            }

            $url = $output_array['code_url'];
            /*$url = explode("/", $url);
            if ($jsapi_arrray['trade_type'] == '0103') {
                $url = "alipays://platformapi/startapp?appId=20000067&url=https%3A%2F%2Fopenauth.alipay.com%2Foauth2%2FpublicAppAuthorize.htm%3Fapp_id%3D2019061065440934%26scope%3Dauth_base%26redirect_uri%3Dhttp%3A%2F%2Falipay.gehehuifu.com%3A6080%2Falipay%2Fzfforpay%2F10001%2F" . $url[6];
            } else if ($jsapi_arrray['trade_type'] == '0102') {
                $url = "alipays://platformapi/startapp?appId=20000067&url=https%3A%2F%2Fopenauth.alipay.com%2Foauth2%2FpublicAppAuthorize.htm%3Fapp_id%3D2019042964379514%26scope%3Dauth_base%26redirect_uri%3Dhttp%253A%252F%252Falipay.tyjfrmy.com%253A7080%252Falipay%252Fzfforpay%252F10000%252F" . $url[6];
            }*/

            if ($data['api_type'] == 1) {
                $this->api_out($data, $url);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                return array('url' => $output_array['prepay_info']);
            }

            if ($data['api_type'] == 2) {
                return array('url' => $output_array['prepay_info']);
            }

            $cash =
                [
                    'ordernumber' => $data['pay_order_number'],
                    'money' => $data['pay_amount'],
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
                return "0102";
            default:
                return "0103";
        }
    }

    public function getOrder($orderList = array())
    {
        $curlTimeOut = 15;
        $key_array = [
            'a20190608793058304' => 'ce81365b279330e06f44b10fbc8dbf0f',
        ];
        $jsapi = array(
            "mch_no" => $orderList['t_account'],
            "out_trade_no" => $this->shared->concat_upstream_order_number($orderList['upstream_order_number'], $orderList['upstream_times']),
        );
        $jsapi['sign'] = $this->md5_class->md5sign($key_array[$orderList['t_account']], $jsapi);
        $jsapi = json_encode($jsapi, 320);

        $output = $this->shared->curl($orderList['query'], $jsapi, '新生', 'BabNhna', 'post', ["Content-type: application/json;charset='utf-8'"], 'off', $curlTimeOut);
        $output_array = json_decode($output, true);

        if ($output_array['result_code'] != 'success') {
            return 3;
        }

        $output_sign = $output_array["sign"];
        unset($output_array["sign"]);

        $sign = strtoupper($this->md5_class->md5sign($key_array[$output_array['mch_no']], $output_array));

        if ($sign != $output_sign) {
            return 2;
        }

        if ($output_array['trade_status'] != '0')
            return 1;

        return 0;
    }

    public function provinceCode($area1, $area2)
    {

        $nhnaProvinceCodeCollection = new NhnaProvinceCodeCollection();
        $provinceCodeList = $nhnaProvinceCodeCollection->getRecordByCondition('area LIKE :area', array('area' => '%' . $area2 . '%'));
        if (empty($provinceCodeList)) {
            $provinceCodeList = $nhnaProvinceCodeCollection->getRecordByCondition('area LIKE :area', array('area' => '%' . $area1 . '%'));
            if (empty($provinceCodeList)) {
                return '110000';
            }
        }

        return $provinceCodeList['province'];
    }

}