<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabHonorProvider extends CashFlowProvider implements CashFlowProviderImp
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

        $data_all = array_merge($pool,$data);
        $data_all["provider"] = "BabHonor";

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

        $jsapi = [
            'banktype' => $this->getBankcode($data['bank_name']),
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
        $msg = '荣耀支付，送往上游字段:'.$jsapi;
        $path_our_log->warn($msg);

        $url = sprintf('%s?%s', $data['address'], $jsapi);
        $output = $this->shared->curl($url, '', '荣耀支付', 'BabHonor', 'get');
        $output = trim($output);

        // TODO: detect error msg in html body
        $match = strpos($output, "form");

        if(!$match) {
            $output_array['msg'] = empty($output)?'请求超时':$output;
            $error_treat = $this->error_treat('荣耀支付','BabHonor', $output, $data, $this->i, $output_array['msg']);
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
            case "1100":
                return "ICBC"; // 工商银行
            case "1101":
                return "ABC"; // 农业银行
            case "1102":
                return "CMB"; // 招商银行
            case "1103":
                return "CIB"; // 兴业银行
            case "1104":
                return "CTTIC"; // 中信银行
            case "1106":
                return "CCB"; // 中国建设银行
            case "1107":
                return "BOC"; // 中国银行
            case "1108":
                return "BOCO"; // 交通银行
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
                return "SHB"; // 上海银行
            case "1118":
                return "NBCB"; // 宁波银行
            case "1119":
                return "PSBS"; // 中国邮储银行
            case "1121":
                return "PINGANBANK"; // 平安银行
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
        return 3;
    }
}