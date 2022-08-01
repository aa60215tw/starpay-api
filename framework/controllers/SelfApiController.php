<?php

require_once(FRAMEWORK_PATH . 'system/controllers/RestController.php');
require_once(FRAMEWORK_PATH . 'collections/PayOrderCollection.php');
require_once(FRAMEWORK_PATH . 'collections/MemberCollection.php');
require_once(FRAMEWORK_PATH . 'collections/PoolCollection.php');
require_once(FRAMEWORK_PATH . 'collections/PoolRuleCollection.php');
require_once(FRAMEWORK_PATH . 'collections/AlipayUserLogCollection.php');
require_once(FRAMEWORK_PATH . 'collections/BlacklistCollection.php');
require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');
require_once(FRAMEWORK_PATH . 'extends/Verification/NowtopayVerification.php');
require_once(FRAMEWORK_PATH . 'extends/CashFlow/CashFlowFactory.php');
require_once(FRAMEWORK_PATH . 'extends/alipay/AlipayH5.php');

class SelfApiController extends RestController
{
    public function completePay($list, $payment_time = '')
    {
        $payOrder_write = new PayOrderCollection('write_db');
        $boolean = $payOrder_write->getById($list['id'])->update(array('payment_status' => '1', 'payment_time' => $payment_time == '' ? date("Y-m-d H:i:s") : $payment_time));
        if ($boolean) {
            $poolCollection_write = new PoolCollection('write_db');
            $poolmodel = $poolCollection_write->getById($list['pool_id']);
            $poolmodel->increaseAttributes(array('success_money' => $list['pay_amount'], 'success_number' => 1));
            $poolmodel->update(array("last_success_time" => date("Y-m-d H:i:s"), 'status' => 1));
            //$wallet = new Wallet();
            $list['payment_status'] = '1';
            //$wallet->wallet_api($list);
            $this->becomePool($list);
            return $list;
        }

        return false;
    }

    public function merchantCall()
    {
        $orderList = $this->receiver;
        $log_success = new LoggerHelper('call_success', CALLBACK_SUCCESS);
        $str = "merchantCall回调下游呼叫成功！订单号：" . $orderList['pay_order_number'];
        $log_success->warn($str);
        if (!$this->call($orderList)) {
            sleep(30);
            if (!$this->call($orderList)) {
                sleep(180);
                if (!$this->call($orderList)) {
                    $payOrder_write = new PayOrderCollection('write_db');
                    $payOrder_write->getById($orderList['id'])->update(array('payment_status' => '3', 'push_time' => date("Y-m-d H:i:s")));
                }
            }
        }
        return array();
    }

    public function call($orderList)
    {
        $jsapi = array(
            $this->user_id => $orderList['user_id'],
            $this->pay_order_number => $orderList['pay_order_number'],
            $this->my_order_number => $orderList['my_order_number'],
            $this->pay_amount => $orderList['pay_amount'],
            $this->orderstatus => 1,
        );
        $collection = new MemberCollection;
        $memberList = $collection->getRecordById($orderList['user_id']);
        ksort($jsapi);
        $md5str = "";
        foreach ($jsapi as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $jsapi[$this->pay_md5sign] = strtoupper(md5($md5str . $memberList['Key']));
        $jsapi[$this->attach] = $orderList['attach'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $orderList['pay_notifyurl']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($jsapi));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        $log_success = new LoggerHelper('call_success', CALLBACK_SUCCESS);
        $str = "call回调下游呼叫成功！订单号：" . $orderList['pay_order_number'] . ",下游回传讯习" . $output . ",我们回传讯习" . json_encode($jsapi, 320);
        $log_success->warn($str);
        $output = trim($output);
        if ($output == "success") {
            $payOrder_write = new PayOrderCollection('write_db');
            $payOrder_write->getById($orderList['id'])->update(array('payment_status' => '2', 'push_time' => date("Y-m-d H:i:s")));
            return true;
        }
        return false;
    }

    public function call_status_push()
    {
        header('Access-Control-Allow-Origin:*');
        if ($this->receiver['pay_order_number'] == '') {
            return array('msg' => 0, 'payorder_number' => '');
        }

        $token = $this->receiver['token'];
        $pay_order_number = $this->receiver['pay_order_number'];
        $url = $_SERVER["HTTP_REFERER"];
        $sign = md5($pay_order_number . AUTH_KEY);
        if (($url != BACK_URL && $url != BACK_URL1) || $token != $sign) {
            return array('msg' => 0, 'payorder_number' => $pay_order_number);
        }

        $data1 = $this->receiver['pay_order_number'];
        $payOrder = new PayOrderCollection();
        $list = $payOrder->getRecord(array('pay_order_number' => $data1));
        $back = $this->call($list);

        if ($back) {
            return array('msg' => 1, 'payorder_number' => $data1);
        }

        return array('msg' => 0, 'payorder_number' => $data1);
    }

    public function search_pay()
    {
        header('Access-Control-Allow-Origin:*');
        if (!empty($this->receiver)) {
            $token = $this->receiver['token'];
            $pay_order_number = $this->receiver['pay_order_number'];
            $url = $_SERVER["HTTP_REFERER"];
            $sign = md5($this->receiver['pay_order_number'] . AUTH_KEY);

            if (($url != BACK_URL && $url != BACK_URL1) || $token != $sign) {
                $data =
                    [
                        'msg' => '4'
                    ];
                return $data;
            }
            $pool = new PoolCollection();
            $path = new PathCollection();
            $payOrder = new PayOrderCollection();
            $order_records = $payOrder->path_order(array('pay_order_number' => $pay_order_number));
            $order_list = $order_records['records'][0];
            $where['t_mid'] = $order_list['t_mid'];
//            $where['user_id'] = $order_list['user_id'];
            $where['path_id'] = $order_list['path_id'];
            $pool_records = $pool->getRecord($where);
            $path_records = $path->get_queryurl(array('id' => $order_list['path_id']));
            $path_queryurl = $path_records['records'][0];

            if (!empty($order_list) && !empty($pool_records) && !empty($path_queryurl)) {

                $data = array_merge($pool_records, $path_queryurl, $order_list);
                $cashFlowFactory = new CashFlowFactory();
                $cashFlow = $cashFlowFactory->create(ucfirst($order_list['swift_path']));
                $search_pay = $cashFlow->getOrder($data);
                if (isset($search_pay)) {
                    if ($search_pay == 0) {
                        $payOrder_write = new PayOrderCollection('write_db');
                        $payOrder_write->getById($order_list['id'])->update(array('payment_status' => 1, 'payment_time' => date("Y-m-d H:i:s")));
                        $this->call($order_list);
                        $data =
                            [
                                'pay_order_number' => $order_list['pay_order_number'],
                                'msg' => '0'
                            ];
                        return $data;
                    } else if ($search_pay == 1) {
                        $data =
                            [
                                'pay_order_number' => $order_list['pay_order_number'],
                                'msg' => '1'
                            ];
                        return $data;
                    } else {
                        $data =
                            [
                                'pay_order_number' => $order_list['pay_order_number'],
                                'msg' => '2'
                            ];
                        return $data;
                    }
                }
            }
        }
        $data =
            [

                'pay_order_number' => $this->receiver['pay_order_number'],
                'msg' => '3'
            ];
        return $data;
    }


    public function search_easy()
    {
        //mlchen
        if (!empty($this->receiver)) {
            $get_value = array_values($this->receiver);
            $ordernumber = array('ordernumber' => $get_value['0']);
            $order = new PayOrderCollection();
            $pool = new PoolCollection();
            $path = new PathCollection();
            $order_records = $order->path_order($ordernumber);
            if (!$order_records['records'])
                return array("status" => 3);

            $order_list = $order_records['records'][0];
            $where['t_mid'] = $order_list['t_mid'];
            $where['path_id'] = $order_list['path_id'];
            $pool_records = $pool->getRecord($where);
            $path_records = $path->get_queryurl(array('id' => $order_list['path_id']));
            $path_queryurl = $path_records['records'][0];
            $data = array_merge($path_queryurl, $pool_records, $order_list);

            $cashFlowFactory = new CashFlowFactory();
            $cashFlow = $cashFlowFactory->create(ucfirst($order_list['swift_path']));
            $search_pay = $cashFlow->getOrder($data);
            return array("status" => $search_pay);
        }
    }


    public function becomePool($list)
    {
        $poolrule = new PoolRuleCollection('write_db');
        $get_array = array('t_mid' => $list['t_mid'], 'pool_user_ip' => $list['user_ip'], 'path_id' => $list['path_id'], 'user_id' => $list['user_id']);
        $result = $poolrule->getRecord($get_array);
        if ($result) {
            $get_array = array('t_mid' => $list['t_mid'], 'pool_user_ip' => $list['user_ip'], 'path_id' => $list['path_id'], 'user_id' => $list['user_id']);
            $poolrule->get($get_array)->update(array('reset_count' => 0));
        } else {
            $insert_data = array(
                'user_id' => $list['user_id'],
                't_mid' => $list['t_mid'],
                'pool_user_ip' => $list['user_ip'],
                'path_id' => $list['path_id'],
                'ipcount' => 1,
                'reset_count' => 0,
                'deal_time_start' => $list['deal_time_start'],
                'deal_time_end' => $list['deal_time_end'],
                'once_deal_money_high' => $list['once_deal_money_high'],
                'once_deal_money_low' => $list['once_deal_money_low']
            );
            $poolrule->create($insert_data);
        }
    }

    public function obtpSend()
    {
        $data = $this->receiver['data'];
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        $cashFlowFactory = new CashFlowFactory();
        $des = new Des();
        $data = $des->desing3des(AUTH_KEY, $data);
        $data = json_decode($data, true);
        if (empty($this->receiver['bank_name'])) {
            $data['message'] = '订单资料缺少';
            $data['message_code'] = '301';
            $this->responser->send($data, $this->responser->InternalServerError());
        }
        $data['bank_name'] = trim($this->receiver['bank_name']);
        $data['member_ip'] = $ip;

        $client_log = new LoggerHelper('obtpSend_log', CLIENT_REQUEST_LOG);
        $msg = json_encode($data, 320);
        $client_log->warn($msg);

        $cashFlow = $cashFlowFactory->create(ucfirst($data['provider']));
        $cashFlow->send_api($data);
    }

    public function zongZhuan()
    {
        $client_log = new LoggerHelper('self_client_log', CLIENT_REQUEST_LOG);
        $client_log->warn(json_encode($this->receiver, 320));

        $id = $this->receiver['id'];
        $tid = substr($id, 0, -3);//订单ID
        if (!$tid) {
            return [];
        }

        $shared = new Shared();
        $token = substr($id, -3);
        $ntoken = $shared->shorturl($tid);
        $retoken = substr($ntoken, -3);

        if ($token != $retoken) {
            return [];
        }

        $cashFlowFactory = new CashFlowFactory();
        $pay_order = new PayOrderCollection('write_db');
        $path = new PathCollection();
        $cashFlowProvider = new CashFlowProvider();

        $pay_order->dao->transaction();
        $pay_order->dao->for_update(true);
        $order_data = $pay_order->getRecordById($tid);

        if (empty($order_data)) {
            return [];
        }

        $data = array(
            'message_code' => '000',
            'message' => '成功',
            'url' => $order_data['qr_url'],
            'swift' => $order_data['swift'],
        );

        /*
        if ($order_data['payment_status'] == -1) {
            $data['message_code'] = '999';
            $this->responser->send($data, $this->responser->OK());
        }*/

        if (!in_array($order_data['payment_status'], ['0', '-1'])) {
            $this->responser->send($data, $this->responser->OK());
        } else {
            $orderUpdateStatus = $pay_order->multipleUpdateByCondition(['id' => $tid], ['payment_status' => '4']);
        }
        $pay_order->dao->commit();

        // 获取 user_id
        $alipay_user_id = '';
        if (!empty($this->receiver['alipay_user_id'])) {
            $alipay_user_id = $this->receiver['alipay_user_id'];
        } elseif (!empty($order_data['alipay_user_id'])) {
            $alipay_user_id = $order_data['alipay_user_id'];
        } elseif (!empty($this->receiver['auth_code'])) {
            $alipayH5 = (empty($this->receiver['alipay_app_id'])) ? new AlipayH5() : new AlipayH5($this->receiver['alipay_app_id']);
            $alipayH5Data = $alipayH5->getUserId($this->receiver['auth_code'], $tid);

            if (isset($alipayH5Data['status']) && $alipayH5Data['status']) {
                $alipay_user_id = $alipayH5Data['user_id'];
            } else {
                $this->creatAlipayFailFile($this->receiver['alipay_app_id']);
                $shared->slack(sprintf('[%s] Alipay H5 无法获取 user_id, 订单号: %s, app_id: %s, %s', $order_data['swift_path'], $order_data['my_order_number'], $this->receiver['alipay_app_id'], gethostname()));
                // 尝试另一组 app_id
                if (!empty($this->receiver['alipay_app_id']) && $this->receiver['alipay_app_id'] != '2018112662286976') {
                    $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2018112662286976&scope=auth_base&redirect_uri=http://open.bondepay.com/jump/z.php?r=" . $this->receiver['r'];
                    $data['url'] = $url;
                    $pay_order->getById($tid)->update(['payment_status' => '0']);
                    $this->responser->send($data, $this->responser->OK());
                }
                // 不尝试另一组 app_id 时，让用户刷新页面
                $this->responser->send($data, $this->responser->OK());
            }
        } else {
            $data['message_code'] = '998';
            $this->responser->send($data, $this->responser->OK());
        }

        $order_data['ip'] = !empty($this->receiver['ip']) ? $this->receiver['ip'] : $order_data['user_ip'];

        // alipay_user_id 从DB 来的话 不写入 log
        if (!empty($alipay_user_id) && empty($order_data['alipay_user_id'])) {
            $alipayUserLogCollection = new AlipayUserLogCollection('write_db');
            $alipayUserLogCollection->create([
                'user_id' => $alipay_user_id,
                'order_id' => $tid,
                'amount' => $order_data['pay_amount'],
                'ip' => $order_data['ip']
            ]);
        }

        if (!empty($alipay_user_id)) {
            // 检查黑名单
            $balcklist = new BlacklistCollection();
            $blackdata = $balcklist->getRecord(['user_id' => $alipay_user_id]);
            if (!empty($blackdata)) {
                $data = [
                    'message_code' => '997',
                    'message' => '失败',
                ];
                $this->responser->send($data, $this->responser->OK());
            }
        }

        $order_data['api_type'] = 2;
        $path_data = $path->getRecordById($order_data['path_id']);
        $order_data['address'] = $path_data['address1'];
        $order_data['turn'] = $path_data['turn'];

        // poolAddOrder($data, $pool_judgment = '', $type = true, $waitTime = 1200, $zong = false, $ip_judgment = true, $strategy_start = 0)
        $pool = '';
        if ($order_data['payment_status'] == -1) {
            $pool_collection = new PoolCollection();
            $pool = $pool_collection->getRecordByCondition('`id` = :id and `status` >= :status', array('id' => $order_data['pool_id'], 'status' => '1'));
        }
        if (empty($pool)) {
            $pool = $cashFlowProvider->poolAddOrder($order_data, $this->receiver['pool_judgment'], false, $this->receiver['wait_time']
                , false, $this->receiver['ip_judgment'], $this->receiver['strategy_start'], $this->receiver['rank'], $this->receiver['districtAdcode']);
        }
        $data_all = array_merge($order_data, $pool);
        $data_all['swift'] = $order_data['swift'];
        $data_all['alipay_user_id'] = $alipay_user_id;
        $data_all['last_id'] = $tid;
        $data_all['payment_status'] = 4;

        $order_up_data = array(
            'pool_id' => $pool['id'],
            't_mid' => $pool['t_mid'],
            't_account' => $pool['t_account'],
            'payment_status' => '4',
            'user_ip' => $order_data['ip'],
            'judgment' => $pool['strategy_index'],
            'alipay_user_id' => $alipay_user_id
        );

        // 重新产生上游订单号
        if ($order_data['payment_status'] == -1) {
            // $data_all['upstream_order_number'] = $shared->new_upstream_order_number($order_data['user_id'], $tid);
            // $order_up_data['upstream_order_number'] = $data_all['upstream_order_number'];
            $data_all['upstream_times'] = intval($order_data['upstream_times']) + 1;
            $order_up_data['upstream_times'] = $data_all['upstream_times'];
        }

        $order_up = $pay_order->getById($tid)->update($order_up_data);
        if (!$order_up) {
            return [];
        }

        $cashFlow = $cashFlowFactory->create(ucfirst($data_all['swift_path']));
        $output = $cashFlow->send_api($data_all);

        if (empty($output['url'])) {
            $this->responser->send($data, $this->responser->OK());
        }

        $order_up = $pay_order->getById($tid)->update(array('qr_url' => $output['url']));
        if (!$order_up) {
            return [];
        }

        $data['url'] = $output['url'];
        $this->responser->send($data, $this->responser->OK());
    }

    public function alipay_jsapi_result()
    {

        $id = $this->receiver['id'];
        $resultCode = $this->receiver['resultCode'];
        $tid = substr($id, 0, -3);//订单ID
        if (!$tid) {
            return false;
        }

        $shared = new Shared();
        $token = substr($id, -3);
        $ntoken = $shared->shorturl($tid);
        $retoken = substr($ntoken, -3);

        if ($token != $retoken) {
            return false;
        }

        if (!empty($resultCode)) {
            $pay_order = new PayOrderCollection('write_db');
            $pay_order->getById($tid)->update(['attach' => $resultCode]);
        }
        $this->responser->send([], $this->responser->OK());
    }

    private function creatAlipayFailFile($app_id)
    {
        if (!$app_id) {
            return;
        }
        $file = ROOT . 'zong/' . $app_id . '.xxx';
        if (!file_exists($file)) {
            $fh = fopen($file, 'w');
            fwrite($fh, date("Y-m-d H:i:s"));
            fclose($fh);
        }
    }

}

?>