<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabComweiProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {
        $pool_judgment = 'all';
        $data["upstream_order_number"] = "";
        $pool = $this->poolAddOrder($data,$pool_judgment);

        if (!$pool) {
            return false;
        }
        $data_all = array_merge($data,$pool);

        $this->send_api($data_all);

    }

    public function send_api($data)
    {
        $MD5 = new Md5();
        $id = $data['t_mid'];
        $type = $this->getBankcode($data['swift']);
        $price = $data["pay_amount"];
        $note = "123";
        $jsapi = array(
            'id'=>$id,
            'type'=>$type,
            'price'=>$price,
            'note'=>$note
        );

        $sign =  $MD5->md5sign($data['key'],$jsapi , 'on' , 'off' , '' , true);
        $jsapi['$sign'] = $sign ;

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = 'CLT，送往上游字段:'.json_encode($jsapi,320);
        $path_our_log->warn($msg);
        $createOrderUrl = $data['address']."?price=$price&note=$note&id=$id&type=$type&sign=$sign";

        $order =  json_decode(file_get_contents($createOrderUrl), 320);;

        if($order['code'] != '100'){
            $error_treat = $this->error_treat('CLT','BabComwei',$order,$data,$this->i,$output['msg']);
            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];
                $this->send_api($data);
            }
        }
        $getOrderUrl = "http://comwei.cn/home/api/getOrder";

        sleep(2);
        $getOrderUrl = $getOrderUrl."?sn=" . $order['sn'] . "&id=" . $data['t_mid'];
        $output =  file_get_contents($getOrderUrl);
        $outputList = json_decode($output , 320);
        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = 'CLT，上游返回字段:'.$output."\r\n";
        $path_up_log->warn($msg);

        if($outputList['state'] != 2){
            sleep(2);
            $getOrderUrl = $getOrderUrl."?sn=" . $order['sn'] . "&id=" . $data['t_mid'];
            $output =  file_get_contents($getOrderUrl);
            $outputList = json_decode($output , 320);
            if($outputList['state'] != 2){
                $error_treat = $this->error_treat('CLT','BabComwei',$order,$data,$this->i,$output['msg']);
                if(!$error_treat['msg']){
                    $this->i++;
                    $data = $error_treat['data'];
                    $this->send_api($data);
                }
            }
        }
            $qrcode_url = stripslashes($outputList['pay_url']);
            $data['upstream_order_number'] = $order['sn'];
            $this->update_order_number($data);
            $this->poolUpdataTransaction($data);

            if($data['api_type'] == 1){
                $this->api_out($data,$qrcode_url);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                header("location:$qrcode_url");
                exit();
            }

            $cash =
                [
                    'ordernumber' => $data['pay_order_number'],
                    'money' => $data['pay_amount'],
                    'banktype' => $data['swift'],
                    'url' => $qrcode_url
                ];
            $this->shared->cash($cash);//轉址收銀台;

    }

    public function getBankcode($swift){
        switch ($swift){
            case "ALIPAY":
                return "alipay";
            case "ALIPAY_APP":
                return "alipay";
            default:
                return "alipay";
        }
    }

    public function getOrder($orderList = array())
    {
        $MD5 = new Md5();
        $jsapi = array(
            "pay_memberid" => $orderList['t_mid'],
            "pay_orderid" => $orderList['upstream_order_number'],
        );
        $sign =  $MD5->md5sign($orderList['key'],$jsapi);
        $jsapi['pay_md5sign'] = $sign ;

        $output = $this->shared->curl($orderList['query'],$jsapi,'马上富','Babpaygw');

        $output_array = json_decode($output,true);

        if($output_array['returncode'] != '00')
            return 3;


        $sign =  $MD5->md5sign($orderList['key'],$output_array);

        if ($sign != $output_array['sign'])
            return 2;

        if($output_array['trade_state'] != 'SUCCESS')
            return 1;

        return 0;
    }
}

?>