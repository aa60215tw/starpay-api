<?php

require_once(FRAMEWORK_PATH . 'system/controllers/RestController.php');
require_once(FRAMEWORK_PATH . 'collections/PayOrderCollection.php');
require_once(FRAMEWORK_PATH . 'collections/MemberCollection.php');
require_once(FRAMEWORK_PATH . 'collections/PoolCollection.php');
require_once(FRAMEWORK_PATH . 'collections/SystemCollection.php');
require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');
require_once(FRAMEWORK_PATH . 'extends/Verification/NowtopayVerification.php');
require_once(FRAMEWORK_PATH . 'extends/Verification/OrderQueryVerification.php');
require_once(FRAMEWORK_PATH . 'extends/CashFlow/CashFlowFactory.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');

class MultipayController extends RestController
{

    public function unifiedpay()
    {
        $client_log = new LoggerHelper('client_log', CLIENT_REQUEST_LOG);
        $message = json_encode($this->receiver, 320);
        $client_log->warn($message);
        $api_post =
            [
                'pay_order_number' => empty($this->receiver[$this->pay_order_number]) ? '' : $this->receiver[$this->pay_order_number],
                'pay_notifyurl' => empty($this->receiver[$this->pay_notifyurl]) ? '' : $this->receiver[$this->pay_notifyurl],
                'swift' => empty($this->receiver[$this->banktype]) ? '' : $this->receiver[$this->banktype],
                'banktype' => empty($this->receiver[$this->banktype]) ? '' : $this->receiver[$this->banktype],
                'pay_amount' => empty($this->receiver[$this->pay_amount]) ? '' : $this->receiver[$this->pay_amount],
                'user_id' => empty($this->receiver[$this->user_id]) ? '' : $this->receiver[$this->user_id],
                'order_time' => date("Y-m-d H:i:s"),
                'pay_md5sign' => empty($this->receiver[$this->pay_md5sign]) ? '' : $this->receiver[$this->pay_md5sign],
                'attach' => empty($this->receiver[$this->attach]) ? '' : $this->receiver[$this->attach],
                'api_type' => empty($this->receiver['api_type']) ? '0' : $this->receiver['api_type'],
                'currency' => empty($this->receiver[$this->currency]) ? '' : $this->receiver[$this->currency]
            ];

        $verification = new NowtopayVerification($api_post);
        $order = $verification->verification_account();

        if ($order['returncode'] == '000') {
            $cashFlowFactory = new CashFlowFactory();
            $cashFlow = $cashFlowFactory->create(ucfirst($order['swift_path']));
            if (!$cashFlow->send($order)) {
                $data['message'] = '系统错误';
                $data['message_code'] = '999';
                $this->responser->send($data, $this->responser->InternalServerError());
            }
        } else {
            $data['message'] = $order['returncode'];
            $data['message_code'] = $order['message_code'];
            $this->responser->send($data, $this->responser->InternalServerError());
        }

        return array();
    }

    public function orderquery()
    {
        $MD5 = new Md5();
        $api_post =
            [
                'pay_order_number' => empty($this->receiver[$this->pay_order_number]) ? '' : $this->receiver[$this->pay_order_number],
                'user_id' => empty($this->receiver[$this->user_id]) ? '' : $this->receiver[$this->user_id],
                'pay_md5sign' => empty($this->receiver[$this->pay_md5sign]) ? '' : $this->receiver[$this->pay_md5sign],
                'search_time' => empty($this->receiver[$this->search_time]) ? '' : $this->receiver[$this->search_time],
            ];

        $verification = new OrderQueryVerification($api_post);
        $order = $verification->verification_account();

        if ($order['returncode'] != '000') {
            $data['message'] = $order['returncode'];
            $data['message_code'] = $order['message_code'];
            $this->responser->send($data, $this->responser->InternalServerError());
        }

        $data_model =
            [
                $this->user_id => $api_post['user_id'],
                $this->pay_order_number => $api_post['pay_order_number'],
                $this->search_time => $api_post['search_time'],
                $this->pay_amount => $order['pay_amount'],
                $this->order_time => $order['order_time'],
                $this->payment_time => $order['payment_time'],
                $this->my_order_number => $order['my_order_number'],
            ];

        $pool = new PoolCollection();
        $path = new PathCollection();
        $payOrder_write = new PayOrderCollection('write_db');
        $pool_records = $pool->getRecordById($order['pool_id']);
        $path_records = $path->get_queryurl(array('id' => $order['path_id']));
        $path_queryurl = $path_records['records'][0];
        if (empty($pool_records) || empty($path_queryurl)) {
            $payOrder_write->getById($order['id'])->update(array('payment_status' => 0));
            $data =
                [
                    'message_code' => '999',
                    'message' => '系統错误，查询失败',
                ];
            $this->responser->send($data, $this->responser->OK());
        }

        $data = array_merge($pool_records, $path_queryurl, $order);

        $cashFlowFactory = new CashFlowFactory();
        $cashFlow = $cashFlowFactory->create(ucfirst($order['swift_path']));
        $search_pay = $cashFlow->getOrder($data);
        if (!isset($search_pay)) {
            $payOrder_write->getById($order['id'])->update(array('payment_status' => 0));
            $data =
                [
                    'message_code' => '999',
                    'message' => '系統错误，查询失败',
                ];
            $this->responser->send($data, $this->responser->OK());
        }

        if ($search_pay == 0) {
            $payOrder_write->getById($order['id'])->update(array('payment_status' => 2, 'push_time' => date("Y-m-d H:i:s"), 'payment_time' => date("Y-m-d H:i:s")));
            $data_model[$this->payment_time] = date("Y-m-d H:i:s");
            $data_model['message_code'] = '000';
            $data_model['message'] = '支付成功';
        } else if ($search_pay == 1) {
            $payOrder_write->getById($order['id'])->update(array('payment_status' => 0));
            $data_model['message_code'] = '400';
            $data_model['message'] = '支付失败!';
        } else {
            $payOrder_write->getById($order['id'])->update(array('payment_status' => 0));
            $data_model['message_code'] = '400';
            $data_model['message'] = '查询失败!!';
        }
        $data_model[$this->pay_md5sign] = $MD5->md5sign($order['Key'],$data_model,'on','on','');
        $this->responser->send($data_model, $this->responser->OK());
    }

}

?>