<?php

require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/alipay/AopSdk.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(dirname(__FILE__) . '/CashFlowProviderImp.php');

class BabF2fProvider extends CashFlowProvider implements CashFlowProviderImp
{

    public function send($data)
    {

        $pool_judgment = 'user_id';
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
        $aop = $this->setAop($data);

        $jsapi = [
            'out_trade_no' =>  $data["upstream_order_number"],
            'total_amount' => $data['pay_amount'],
            'subject' => "业务订单号:" . $data["my_order_number"],
        ];
        $request = new AlipayTradePrecreateRequest();
        $request->setNotifyUrl($data['turn']);
        $request->setBizContent(json_encode($jsapi, 320));

        $path_our_log = new LoggerHelper('path_our_log', PATH_REQUEST_LOG);
        $msg = '當面付，送往上游字段:'.json_encode($data,320);
        $path_our_log->warn($msg);

        $result = $aop->execute($request);

        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = '當面付，上游返回字段:'.json_encode($result,320);
        $path_up_log->warn($msg);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";

        if (!(property_exists($result, $responseNode)
            && property_exists($result->$responseNode, 'qr_code')))
        {
            if (property_exists($result, $responseNode)) {
                $error = $result->$responseNode->sub_msg;
            }
            if (property_exists($result, 'error_response')) {
                $error = $result->error_response->sub_msg;
            }
            $error_treat = $this->error_treat('當面付','BabF2f',json_encode($result,320),$data,$this->i,$error);

            if(!$error_treat['msg']){
                $this->i++;
                $data = $error_treat['data'];

                $this->send_api($data);
            }
        } else{
            if($this->i != 0){
                $this->update_order_number($data);
            }

            $this->poolUpdataTransaction($data, 1);

            $url = $result->$responseNode->qr_code;
            if($data['api_type'] == 1){
                $this->api_out($data,$url);
            }

            if ($data['swift'] == "ALIPAY_APP") {
                return array('url' => $url);

            }

            if($data['api_type'] == 2){
                return array('url' => $url);
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
                return "ALIPAY";
            default:
                return "ALIPAY";
        }
    }

    public function getOrder($orderList = array()){
        $aop = $this->setAop($orderList);
        $data = [
            'out_trade_no' => $orderList,
        ];
        $request = new AlipayTradeQueryRequest();
        $request->setBizContent(json_encode($data, 320));

        $result = $aop->execute($request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        if (property_exists($result, $responseNode)
            && property_exists($result->$responseNode, 'trade_status'))
        {
            if(!$result->$responseNode->trade_status == 'TRADE_SUCCESS'){
                return 1;
            }
        }else{
            return 3;
        }

        return 0;
    }

    private function setAop($data){
        $aop = new AopClient();
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = $data['t_mid'];
        $aop->rsaPrivateKey =  $data['rsakey'];
        $aop->alipayrsaPublicKey = $data['key1'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        return $aop;
    }

}

?>