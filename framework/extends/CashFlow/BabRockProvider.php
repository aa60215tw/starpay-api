<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabRockProvider extends CashFlowProvider implements CashFlowProviderImp
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
        $data_all["provider"] = "BabRock";
//        $newData = array("my_order_number" => $data_all['my_order_number'], "pay_amount" => $data_all['pay_amount']
//        , "turn" =>$data_all['turn'] , "address" => $data_all['address'] , "provider" => "BabRock" , "obtp_code" => $data_all['obtp_code']);
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
        $jsapi = array(
            'merchant_id' => $data["t_mid"],
            'timestamp' => time(),
            'uuid' => '1234',
            'version'=>'3.0.0',
            'sign_type'=>'MD5',
            'bank_code' => $this->getBankcode($data['bank_name']),
            'bank_name' => '工商银行',
            'bank_account' => '刘平',
            'bank_no' => '6222033100014588860',
            'phone' => '18623079407',
            'cert_type' => 'id_card',
            'cert_no' => '510223198307143738',
            'order_no' => $data["upstream_order_number"],
            'currency' => 'CNY',
            'amount' => $data["pay_amount"]*100,
            'notify_url' => $data['turn'],
            'redirect_url' => $data['turn'],
            'clear_time' => 'T0',
        );

        ksort($jsapi);
        $md5str = http_build_query($jsapi);
        $md5str = urldecode($md5str);
        $sign = md5($md5str.md5($data['key']));

        $jsapi['sign']=$sign;
        $jsapi = json_encode($jsapi,320);

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '钜石网银，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);

        $header = array(
            'Content-Type: application/json',
        );
        $output = $this->shared->curl($data['address'], $jsapi,'钜石网银','BabRock','post',$header);
        $output = trim($output);

        $output_array = json_decode($output,true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '钜石网银，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);

        if($output_array['code'] != 'RP0000'){
            $output_array['msg'] = empty($output_array['msg'])?'请求超时':$output_array['msg'];
            $error_treat = $this->error_treat('钜石网银','BabRock',$output,$data,$this->i,$output_array['msg']);
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
            $url = urldecode($output_array['url']);

            header("location:$url");
            exit();

        }

    }

    public function getBankcode($swift){
        switch ($swift){
            case "1108":
                return "COMM";
            case "1106":
                return "CCB";
            case "1102":
                return "CMB";
            case "1110":
                return "CMBC";
            case "1101":
                return "ABC";
            case "1100":
                return "ICBC";
            case "1107":
                return "BOC";
            case "1109":
                return "SPDB";
            case "1114":
                return "GDB";
            case "1112":
                return "CEB";
            case "1119":
                return "PSBC";
            case "1104":
                return "CITIC";
            default:
                return "CMB";
        }
    }

    public function getOrder($orderList = array())
    {
        $jsapi = array(
            'merchant_id' => $orderList["t_mid"],
            'timestamp' => time(),
            'uuid' => '1234',
            'version'=>'3.0.0',
            'sign_type'=>'MD5',
            'order_no'=>$orderList['upstream_order_number'],
        );

        ksort($jsapi);
        $md5str = http_build_query($jsapi);
        $md5str = urldecode($md5str);
        $sign = md5($md5str.md5($orderList['key']));

        $jsapi['sign']=$sign;
        $jsapi = json_encode($jsapi,320);
        $header = array(
            'Content-Type: application/json',
        );
        $output = $this->shared->curl($orderList['query'],$jsapi,'钜石网银','BabRock','post',$header);

        $output_array = json_decode($output,true);

        if($output_array['code'] != 'RP0000')
            return 3;

        $output_sign = $output_array['sign'];
        unset($output_array['sign']);
        ksort($output_array);
        $md5str = http_build_query($output_array);
        $md5str = urldecode($md5str);
        $sign = md5($md5str.md5($orderList['key']));

        if ($sign != $output_sign)
            return 2;

        if($output_array['status'] != '0')
            return 1;

        return 0;
    }
}

?>