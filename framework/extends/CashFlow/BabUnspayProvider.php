<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabUnspayProvider extends CashFlowProvider implements CashFlowProviderImp
{
    protected $provider_name = '';
    protected $log_name = '';
    protected $curl_header = [];

    public function __construct() {
        parent::__construct();
        $fake_ip = '35.194.228.95';
        $this->provider_name = 'BabUnspay';
        $this->log_name = '银生宝';
        $this->curl_header = [
            'CLIENT-IP:'.$fake_ip,
            'X-FORWARDED-FOR:'.$fake_ip,
            'REMOTE_ADDR:'.$fake_ip,
            'HTTP_X_FORWARDED_FOR:'.$fake_ip
        ];
    }

    public function send($data)
    {
        $des = new Des();
        $pool_judgment = 'single';
        $upstream_order_number = $this->shared->new_upstream_order_number($data['user_id']);
        $data["upstream_order_number"] = $upstream_order_number;
        $pool = $this->poolAddOrder($data, $pool_judgment, false);
        if (!$pool) {
            return false;
        }

        $data_all = array_merge($pool,$data);
        $data_all["provider"] = $this->provider_name;

        ob_clean();
        echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.ShowForm.submit();\">";
        echo "<form name=\"ShowForm\" method=\"post\" action=\"".OBTP_URL."\" target=\"_self\">";
        echo "<input type=\"hidden\" name=\"data\" value=\"".base64_encode($des->sign3des(AUTH_KEY,json_encode($data_all,320)))."\"/>";
        echo "</form></body></html>";
        ob_end_flush();
        exit();
    }

    public function send_api($data, $type=true)
    {
        try {
            if($type){
                $orderList = $this->addOrder($data);
                if (!$orderList) {
                    return false;
                }
                $data = array_merge($orderList ,$data);
            }

            $jsapi = array_merge(
                $this->baseParam('netbank_pay', $data['t_mid'], $data['turn']),
                [
                    'out_order_no' => $data['upstream_order_number'],
                    'merchant_no' => $data['t_mid'],
                    'amount' => round($data['pay_amount'],2),
                    'pay_inst' => $this->getBankcode($data['bank_name']),
                    'card_type' => 'DC',
                ]
            );

            $this->md5sign($jsapi, $data['key']);

            $jsapi = http_build_query($jsapi);

            // log
            $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
            $path_our_log->warn(sprintf('%s，送往上游字段:%s', $this->log_name ,$jsapi));

            $output = $this->curlWithHeader($data['address'], $jsapi);

            $result = json_decode($output, true);

            $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
            $path_up_log->warn(sprintf("%s，上游返回字段:%s \r\n上游返回字段JSON:%s", $this->log_name ,$output,json_encode($result,320)));

            if ($result['error_code'] != "0") {
                $output_array['msg'] = empty($result) ? '请求超时': $result['error_code'] . $result['error_message'];
                $error_treat = $this->error_treat($this->log_name, $this->provider_name, $output, $data, $this->i, $output_array['msg']);
                if(!$error_treat['msg']){
                    $this->i++;
                    $data = $error_treat['data'];
                    $this->send_api($data, false);
                }
            } else{

                if($this->i != 0){
                    $this->update_order_number($data);
                }
                // TODO check sign

                $this->poolUpdataTransaction($data);

                ob_clean();
                echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.frmBankName.submit();\">";
                echo $result['post_data'];
                echo "</body></html>";
                ob_end_flush();
                exit();
            }
        } catch(\Throwable $e) {
            $path_our_log->error(sprintf('%s，Exception :%s', $this->log_name ,$e->getMessage()));
        }
    }

    private function curlWithHeader($address, $jsapi)
    {
        return $this->shared->curl(
            $address,
            $jsapi,
            $this->log_name,
            $this->provider_name,
            'post',
            $this->curl_header
        );
    }

    private function baseParam($service, $merchant_no, $callbackurl)
    {
        $data = [
            'service' => $service,
            'version' => "1.0",
            'request_time' => date("YmdHis"),
            'partner_id' => $merchant_no,
            '_input_charset' => 'utf-8',
            'notify_url' => $callbackurl,
        ];
        if (empty($data['notify_url'])) {
            unset($data['notify_url']);
        }
        return $data;
    }

    private function md5sign(&$param, $key)
    {
        unset($param['sign'],$param['sign_type']);
        ksort($param);
        $sign_string = urldecode(http_build_query($param));
        $param['sign'] = md5($sign_string.$key);
        $param['sign_type'] = "MD5";
    }

    public function getBankcode($swift){
        switch ($swift){
            case "1100":
                return "ICBC"; // 工商银行
            case "1101":
                return "ABC"; // 农业银行
            case "1102":
                return "CMB"; // 招商银行
            case "1103":
                return "CIB"; // 兴业银行
            case "1104":
                return "CITIC"; // 中信银行
            case "1106":
                return "CCB"; // 中国建设银行
            case "1107":
                return "BOC"; // 中国银行
            case "1108":
                return "COMM"; // 交通银行
            case "1109":
                return "SPDB"; // 浦发银行
            case "1110":
                return "CMBC"; // 民生银行
            case "1111":
                return "HXB"; // 华夏银行
            case "1112":
                return "CEB"; // 光大银行
            case "1113":
                return "BCCB"; // 北京银行
            case "1114":
                return "GDB"; // 广发银行
            case "1115":
                return "NJCB"; // 南京银行
            case "1116":
                return "BOS"; // 上海银行
            case "1118":
                return "NBCB"; // 宁波银行
            case "1119":
                return "PSBC"; // 中国邮储银行
            case "1121":
                return "SZPAB"; // 平安银行
            case "1122":
                return "HKBEA"; // 东亚银行
            case "1123":
                return "CBHB"; // 渤海银行
            default:
                return "ICBC";
        }
    }

    public function getOrder($orderList = array())
    {
        // get order
        $jsapi = $this->baseParam('query_pay_order', $orderList['t_mid'], '');
        $jsapi['order_no'] = $orderList['upstream_order_number'];

        $this->md5sign($jsapi, $orderList['key']);

        $jsapi = http_build_query($jsapi);

        $output = $this->curlWithHeader($orderList['query'], $jsapi);
        $result = json_decode($output, true);

        // TODO check sign
        $output_sign = $result['sign'];
        $this->md5sign($result, $orderList['key']);

        if ($result['error_code'] != 0){
            return 3;
        } elseif ($result['sign'] != $output_sign) {
            return 2;
        } elseif ($result['trans_status'] == 2) {
            return 0;
        } elseif ($result['trans_status'] == 3) {
            return 1;
        } elseif ($result['trans_status'] == 1) {
            return 1;
        } else {
            return 3;
        }

        return 3;
    }
}