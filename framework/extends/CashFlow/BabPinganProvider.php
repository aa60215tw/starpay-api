<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabPinganProvider extends CashFlowProvider implements CashFlowProviderImp
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
        $j_params = array(
            'open_id'=>$data["t_mid"],
            'timestamp'=>time(),
            'pmt_tag'=>$this->getBankcode($data['swift']),
            'out_no'=> $data['upstream_order_number'],
            'original_amount'=>(string) ($data["pay_amount"]*100),
            'trade_amount'=>(string) ($data["pay_amount"]*100),
            'notify_url'=>$data['turn'],
        );
        $param_str = json_encode($j_params,JSON_UNESCAPED_UNICODE);
        $j_data = aes_encrypt($param_str,$data["key"]);

        $jsapi = array(
            'open_id'=>$data["t_mid"],
            'timestamp'=>time(),
            'data'=>$j_data,
        );
        $jsapi['open_key'] = $data["key"];
        ksort($jsapi);
        $sign = md5(sha1(http_build_query($jsapi)));
        $jsapi['sign'] = $sign;
        unset($jsapi["open_key"]);
        $jsapi = http_build_query($jsapi);


        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '平安，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);

        $output = $this->shared->curl($data['address'], $jsapi,'平安','BabPingan','post');
        $output_array=json_decode($output,true);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '平安，上游返回字段:'.$output."\r\n".'上游返回字段JSON:'.json_encode($output_array,320);
        $path_up_log->warn($msg);

        if ($output_array['errcode'] != '0'){
            $error_treat = $this->error_treat('平安','BabPingan',$output,$data,$this->i,$output_array['msg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }else{
            if ($this->i != 0) {
                $this->update_order_number($data);
            }
            //验证签名
            $output_sign = $output_array["sign"];
            unset($output_array["sign"]);
            $output_array["open_key"] = $data["key"];
            ksort($output_array);
            $outputPars = urldecode(http_build_query($output_array));
            $outputSign = md5(sha1($outputPars));
            if($output_sign!=$outputSign){
                $this->upstream_error_msg('BabPingan',$output_array.'验签错误');
                $this->error_msg('平安',$output_array.'验签错误',$data,'上游返回字段验签错误');
            }
            //解密返回data
            $data_decrypt = json_decode(aes_decrypt($output_array["data"],$data["key"]),true);

            $this->poolUpdataTransaction($data);

            $url = $data_decrypt['trade_qrcode'];
            if ($data['api_type'] == 1) {
                $this->api_out($data,$url);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                header("location:$url");
                exit();
            }

            $cash =
                [
                    'ordernumber' => $data['pay_order_number'],
                    'money' => $data["pay_amount"],
                    'banktype' => $data['swift'],
                    'url' => $url
                ];
            $this->shared->cash($cash);//轉址收銀台
        }


    }

    public function getBankcode($swift){
        switch ($swift){
            case "ALIPAY":
                return "AlipayCS";
            case "ALIPAY_APP":
                return "AlipayCS";
            case "WXPAY":
                return "WeixinOL";
            case "WXPAY_APP":
                return "WeixinOL";
            case "QQPAY":
                return "QpayCS";
            case "QQPAY_APP":
                return "QpayCS";
            case "JDPAY":
                return "Jdpay";
            case "JDPAY_APP":
                return "Jdpay";
            default:
                return "AlipayCS";
        }
    }

    public function getOrder($orderList = array())
    {
        $j_params = array(
            'open_id'=>$orderList['t_mid'],
            'timestamp'=>time(),
            'out_no'=> $orderList['upstream_order_number'],
        );
        $param_str = json_encode($j_params,JSON_UNESCAPED_UNICODE);
        $j_data = aes_encrypt($param_str,$orderList['key']);

        $jsapi = array(
            'open_id'=>$orderList['t_mid'],
            'timestamp'=>time(),
            'data'=>$j_data,
        );
        $jsapi['open_key'] = $orderList['key'];
        ksort($jsapi);
        $sign = md5(sha1(http_build_query($jsapi)));
        $jsapi['sign'] = $sign;
        unset($jsapi["open_key"]);
        $jsapi = http_build_query($jsapi);

        $output = $this->shared->curl($orderList['query'], $jsapi,'平安','BabPingan','post');
        $output_array=json_decode($output,true);


        if($output_array['errcode']=='0') {
            //验证签名
            $output_sign = $output_array["sign"];
            unset($output_array["sign"]);
            $output_array["open_key"] = $orderList["key"];
            ksort($output_array);
            $outputPars = urldecode(http_build_query($output_array));
            $outputSign = md5(sha1($outputPars));
            if($output_sign == $outputSign){
                $result_data = json_decode(aes_decrypt($output_array["data"],$orderList["key"]),true);
                switch ($result_data['status']) {
                    case "2"://还未支付
                        return 1;
                        break;
                    case "1"://支付成功
                        return 0;
                        break;
                    default :
                        return 2;
                }
            }else{
                $this->upstream_error_msg('BabPingan',$output_array.'验签错误');
                $this->error_msg('平安',$output_array.'验签错误',$orderList,'上游返回字段验签错误');
                return 3;
            }
        }
        return 3;
    }
}

function aes_encrypt($input, $key){
    $data = openssl_encrypt($input, 'AES-256-ECB', $key, OPENSSL_RAW_DATA);
    $data = bin2hex($data);
    return $data;
}

function aes_decrypt($sStr, $sKey){
    $decrypted = openssl_decrypt(hex2bin($sStr), 'AES-256-ECB', $sKey, OPENSSL_RAW_DATA);
    return $decrypted;
}


?>