<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabLiqiangProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $pool_judgment = 'all';
        $upstream_order_number = $this->shared->new_upstream_order_number($data['user_id']);
        $data["upstream_order_number"] = $upstream_order_number;
        $pool = $this->poolAddOrder($data,$pool_judgment);
        if (!$pool) {
            return false;
        }
        $data_all = array_merge($data,$pool);
        $this->send_api($data_all);
    }

    public function send_api($data)
    {
        $Md5 = new Md5();
        $jsapi = array(
            "outOid" => $data["upstream_order_number"],
            "merchantCode" => $data['t_mid'],
            "payType" => $this->getBankcode($data['swift']),
            "payAmount" => $data['pay_amount']*100,
            "goodName" => $data["my_order_number"],
        );

        $jsapi['sign'] = $Md5->md5sign($data["key"],$jsapi);
        $jsapi['notifyUrl'] = $data['turn'];

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '力强，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'], $jsapi,'力强','Babliqiang');
        $output = trim($output);

        $output_array = json_decode($output,true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '力强，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);

        if($output_array['code'] != '000000'){
            $output_array['msg'] = empty($output_array['msg'])?'请求超时':$output_array['msg'];
            $error_treat = $this->error_treat('力强','Babliqiang',$output,$data,$this->i,$output_array['msg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        } else{
            $url = $output_array['value']['payUrl'];
            if($data['swift'] == 'ALIPAY' || $data['swift'] == 'ALIPAY_APP'){
                $url = str_replace("beforeOrderPay","orderPay",$url);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                $Headers = curl_getinfo($ch);
                curl_close($ch);
                $url = $Headers['redirect_url'];
                $match = strpos($url,"alipay");

                if(!$match) {
                    $error_treat = $this->error_treat('力强', 'Babliqiang', $output, $data, $this->i, '上游QRCODE地址返回错误');
                    if (!$error_treat['msg']) {
                        $this->i++;
                        $data = $error_treat['data'];
                        $this->send_api($data);
                    }
                }
            }
            if($this->i != 0){
                $this->update_order_number($data);
            }

            $output_value = $output_array['value'];
            unset($output_value['extend1'],$output_value['extend2'],$output_value['extend3']);
            $sign = $Md5->md5sign($data["key"],$output_value);
            if ($sign != $output_array['sign']) {
                $this->upstream_error_msg('Babliqiang',$output.'验签错误');
                $this->error_msg('力强',$output.'验签错误',$data,'上游返回字段验签错误');
            }

            $this->poolUpdataTransaction($data);

            if($data['api_type'] == 1){
                $this->api_out($data,$url);
            }

            if ($data['swift'] == "ALIPAY_APP" || $data['swift'] == "QQPAY_APP" || $data['swift'] == "OBTP") {
                header("location:$url");
                exit();
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
    }

    public function getBankcode($swift){
        switch ($swift){
            case "ALIPAY":
                return "44";
            case "ALIPAY_APP":
                return "44";
            case "WXPAY":
                return "43";
            case "WXPAY_APP":
                return "43";
            case "QQPAY":
                return "26";
            case "QQPAY_APP":
                return "26";
            case "OBTP":
                return "60";
            default:
                return "60";
        }
    }

    public function getOrder($orderList = array()){
        $Md5 = new Md5();
        $jsapi = array(
            "outOid" => $orderList["upstream_order_number"],
            "platformOid" => '',
            "merchantCode" => $orderList['t_mid'],
            "payAmount" => $orderList['pay_amount']*100,
            "orderDate" => date("Y-m-d",strtotime($orderList['order_time'])),
        );

        $jsapi['sign'] = $Md5->md5sign($orderList["key"],$jsapi);

        $output = $this->shared->curl($orderList['query'], $jsapi,'力强','Babliqiang');
        $output = trim($output);

        $output_array = json_decode($output,true);

        if($output_array['code'] != '000000')
            return 3;

        $output_value = $output_array['value'];
        $sign = $Md5->md5sign($orderList["key"],$output_value);
        if ($sign != $output_array['sign'])
            return 2;

        if($output_value['payStatus'] != '2')
            return 1;

        return 0;
    }
}

?>