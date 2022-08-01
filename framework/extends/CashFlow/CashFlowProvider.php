<?php
require_once(FRAMEWORK_PATH . 'collections/PoolCollection.php');
require_once(FRAMEWORK_PATH . 'collections/ErrorMsgCollection.php');
require_once(FRAMEWORK_PATH . 'collections/PoolRuleCollection.php');
require_once(FRAMEWORK_PATH . 'collections/ChinaListCollection.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');

class CashFlowProvider
{

    public $shared;
    public $i = 1;

    public function __construct()
    {
        $this->shared = new Shared;
    }

    public function poolAddOrder($data, $pool_judgment = '', $type = true, $waitTime = 1200, $zong = false, $ip_judgment = false, $strategy_start = 0, $rank = true, $districtAdcode = null)
    {

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        }
        $ip = (!empty($data['ip'])) ? $data['ip'] : $ip;

        if ($zong) {
            $data['member_ip'] = $ip;

            $addOrder = $this->addOrder($data);
            if (!$addOrder) {
                return false;
            };

            $data['my_order_number'] = $addOrder["my_order_number"];
            $ntoken = $this->shorturl($addOrder["last_id"]);
            $retoken = substr($ntoken, -3);
            $r = [
                'tid' => $addOrder["last_id"] . $retoken,
                'pool_judgment' => $pool_judgment,
                'wait_time' => $waitTime,
                'ip_judgment' => $ip_judgment,
                'strategy_start' => $strategy_start,
                'rank' => $rank,
            ];
            $r = base64_encode(json_encode($r, 320));
            $url = "alipays://platformapi/startapp?appId=20000067&url=" . ZONG_JUMP_FULL_URL . "?r=" . $r;
            //$url = "alipays://platformapi/startapp?appId=20000067&url=http://35.194.249.119/jump/jump.php?r=" . $r;
            //$url = "alipays://platformapi/startapp?appId=20000067&url=" . urlencode("https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2018121262550151&scope=auth_base&redirect_uri=http://open.bondepay.com/jump/z.php?r=" . $r);
            if ($data['api_type'] == 1) {
                $this->api_out($data, $url);
            }

            if (substr($data['swift'], -3) == "APP") {
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

        if ($districtAdcode != '' && substr($districtAdcode, 2) != '0000') {
            $data['area1'] = "";
            $data['area2'] = "";
            $data['area3'] = "";
            $chinaListCollection = new ChinaListCollection();
            $chinaList = $chinaListCollection->getChina(["districtAdcode" => $districtAdcode]);
            if ($chinaList) {
                $area1 = substr($districtAdcode, 0, 2) . "0000";
                $area2 = substr($districtAdcode, 0, 4) . "00";
                foreach ($chinaList as $value) {
                    if ($value['AreaCode'] == $area1) {
                        $data['area1'] = mb_substr($value['AreaName'], 0, 2, 'utf-8');
                    }
                    if ($value['AreaCode'] == $area2) {
                        $data['area2'] = mb_substr($value['AreaName'], 0, 2, 'utf-8');
                    }
                    if ($value['AreaCode'] == $districtAdcode) {
                        $data['area3'] = $value['AreaName'];
                    }
                }
            }
        }
        if ($data['area1'] == '' || $data['area2'] == '' ) {
            if ($data['member_ip'] == '' || $data['member_ip'] != $ip) {
                $areaList = $this->shared->getipadd($ip);
                $data['area1'] = mb_substr($areaList['region'], 0, 2, 'utf-8');
                $data['area2'] = mb_substr($areaList['city'], 0, 2, 'utf-8');
                $data['area3'] = "";
                //            $memberCollection_write = new MemberCollection('write_db');
                //            $memberCollection_write->getByCondition('user_id=:user_id', array('user_id' => $data['user_id']))
                //                ->update(array(
                //                    "member_ip" => $data['member_ip'],
                //                    "area1" => $data['area1'],
                //                    "area2" => $data['area2']
                //                ));
            }
        }

        $regionkey = "广东省,湖北省,重庆,山西省,四川省,湖南省,广西壮族自治区,福建省,浙江省,江苏省,云南省,辽宁省,江西省,海南省,贵州省,河北省,山东省,上海,安徽省,陕西省,北京,黑龙江省,甘肃省,广东省,河南省,吉林省,青海省,天津,西藏自治区,新疆维吾尔自治区,内蒙古自治区,宁夏回族自治区,台湾,中国";
        $region = $data['area1'];
        if ($region == '' || strstr($regionkey, $region) == '') {//找不到
            $ip_log = new LoggerHelper('ip_log', IP_LOG);
            $msg = " ip:" . $ip . " " . $region;
            $ip_log->warn($msg);
            $data['area1'] = "other";
            $data['area2'] = "other";
            $data['area3'] = "other";
//            if($data['swift'] == 'ALIPAY_APP'){
//                $msg =
//                    [
//                        'message' => '地区非内地',
//                        'message_code' => '996'
//                    ];
//                $multipay = new MultipayController();
//                $multipay->responser->send($msg, $multipay->responser->InternalServerError());
//            }
        }


        $data['member_ip'] = $ip;
        $poolCollection = new PoolCollection();
        $data['today_use'] = true;
        $data['today_money'] = true;

        $data_search = $data;
        $pool_exist = false;

        if ($ip_judgment) {
            $poolRuleCollection = new PoolRuleCollection();
            $pool_exist = $poolRuleCollection->getPoolrecord($data_search);
        }

        if ($pool_judgment != '') {
            $pool_judgment_array = explode(',', $pool_judgment);
            foreach ($pool_judgment_array as $key => $val) {
                unset($data_search[$val]);
            }
        }
        $data_search['swift'] = str_replace("_APP", "", $data_search['swift']);
        if ($pool_judgment == 'all') {
            $poolList = $poolCollection->getRecords(array(
                'path_id' => $data['path_id'],
                'swift' => $data_search['swift'],
                'status' => '1'
            ), 1, 2, array(), 'last_use_time');
            $poolList = $poolList['records'][0];
            $poolList['pool_id'] = $poolList['id'];
        } elseif ($pool_judgment == 'single') {
            $poolList = $poolCollection->getRecords(array(
                'user_id' => $data['user_id'],
                'path_id' => $data['path_id'],
                'swift' => $data_search['swift'],
                'status' => '1'
            ), 1, 2, array(), 'last_use_time');
            $poolList = $poolList['records'][0];
            $poolList['pool_id'] = $poolList['id'];
        } elseif (isset($pool_exist) && !empty($pool_exist)) {
            $pool_exist = $pool_exist['0'];
            $poolList = $poolCollection->getRecord(array(
                'user_id' => $pool_exist['user_id'],
                't_mid' => $pool_exist['t_mid'],
                'path_id' => $pool_exist['path_id'],
                'status' => '1'
            ));
            $poolList['pool_id'] = $poolList['id'];
        }

        //查水池策略
        if (empty($poolList['pool_id']) && $pool_judgment != 'single') {
            if ($data['area3'] != '') {
                $strategy_start = -1;
            }
            $poolList = $this->poolStrategy($poolCollection, $data_search, $data, $waitTime, $strategy_start);
        }

        if (empty($poolList['pool_id'])) {
            $msg =
                [
                    'message' => '无可用水池',
                    'message_code' => '900'
                ];
            $this->shared->slack($data['swift_path'] . ":" . $msg['message'], SLACK_POOL_URL);
            $multipay = new MultipayController();
            $multipay->responser->send($msg, $multipay->responser->InternalServerError());
        }

        $data["order_time"] = date("Y-m-d H:i:s");
        $data["t_mid"] = $poolList['t_mid'];
        $data["t_account"] = $poolList['t_account'];
        $data['pool_id'] = $poolList['pool_id'];
        $data['judgment'] = $poolList['strategy_index'];
        $data['referer'] = $_SERVER["HTTP_REFERER"];
        $poolList['referer'] = $_SERVER["HTTP_REFERER"];

        if ($poolList['area1'] == '' && $poolList['area2'] == '') {
            $rank_data = $this->rank($data_search);
            $poolList['area1'] = $data["area1"];
            $poolList['area2'] = $data["area2"];
            if ($rank) {
                $poolList['once_deal_money_low'] = $rank_data[0];
                $poolList['once_deal_money_high'] = $rank_data[1];
//            $poolList['deal_time_start'] = $rank[2];
//            $poolList['deal_time_end'] = $rank[3];
            }
        }

        $poolCollection_write = new PoolCollection('write_db');
        $poolCollection_write->getById($poolList['pool_id'])->update(array(
            "last_use_time" => date("Y-m-d H:i:s"),
            "area1" => $poolList['area1'],
            "area2" => $poolList['area2'],
            "once_deal_money_low" => $poolList['once_deal_money_low'],
            "once_deal_money_high" => $poolList['once_deal_money_high'],
            "deal_time_start" => $poolList['deal_time_start'],
            "deal_time_end" => $poolList['deal_time_end'],
        ));

        if ($type) {
            $addOrder = $this->addOrder($data);
            if (!$addOrder) {
                return false;
            };
            $pool_new = array_merge($poolList, $addOrder);

            return $pool_new;
        }
        return $poolList;
    }

    public function addOrder($data)
    {
        $collection_write = new PayOrderCollection('write_db');
        if ($data['fee_status'] == 1) {
            $pay_actualamount = $data['pay_amount'] - $data['fee'];
            $fee = $data['fee'];
        } else {
            if ($data['fee_status'] == 2) {
                $pay_actualamount = $data['pay_amount'] - ($data['pay_amount'] * $data['fee']) / 1000;
                $fee = ($data['pay_amount'] * $data['fee']) / 1000;
            }
        }
        $now = date("YmdHis");

        $attributes = array(
            "user_id" => $data['user_id'],
            "pay_order_number" => $data['pay_order_number'],
            "swift" => $data['swift'],
            "swift_path" => $data['swift_path'],
            "path_id" => $data['path_id'],
            "pay_amount" => $data['pay_amount'],
            "fee" => $fee,
            "fee_status" => $data['fee_status'],
            "pay_actualamount" => $pay_actualamount,
            "order_time" => $data['order_time'],
            "pay_notifyurl" => $data['pay_notifyurl'],
            "upstream_order_number" => $data['upstream_order_number'],
            "upstream_times" => 1,
            "t_mid" => $data['t_mid'],
            "t_account" => $data['t_account'],
            "my_order_number" => $now . $this->shared->bab_rand() . $data['user_id'],
            "referer" => $data['referer'],
            "user_ip" => $data['member_ip'],
            "attach" => $data['attach'],
            "pool_id" => $data['pool_id'],
        );
        if (!empty($data["bank_name"])) {
            $attributes['otpb_code'] = $data["bank_name"];
        }
        if (isset($data["judgment"])) {
            $attributes['judgment'] = $data["judgment"];
        }

        try {
            $collection_write->dao->transaction();
            $order["effectRow"] = $collection_write->create($attributes);

            if ($order["effectRow"] != 1) {
                return false;
            }
            $last_id = $collection_write->dao->lastInsertId();
            $this->writeOrder($data['pay_order_number']);
            $attributes['last_id'] = $last_id;

            // 更新订单号 预防重覆
            $attributes['my_order_number'] = $now . substr($last_id, -3) . $data['user_id'];
            $attributes['upstream_order_number'] = $this->shared->new_upstream_order_number($data['user_id'], $last_id);
            $collection_write->getById($last_id)->update([
                'upstream_order_number' => $attributes['upstream_order_number'],
                'my_order_number' => $attributes['my_order_number'],
            ]);
            $collection_write->dao->commit();

            return $attributes;

        } catch (Throwable $t) {
            $collection_write->dao->rollback();
            return false;
        } catch (Exception $e) {
            $collection_write->dao->rollback();
            return false;
        }
    }

    public function upstream_error_msg($name = '', $output = '')
    {
        $upstream_log = new LoggerHelper($name . '_log', UPSTREAM_LOG);
        $msg = $name . "回传错误讯息：" . $output;
        $upstream_log->warn($msg);
    }

    public function error_msg($ch_name, $output, $data = array(), $error_code = '', $special = '')
    {
        $error_write = new ErrorMsgCollection('write_db');
        $multipay = new MultipayController();

        if (!empty($data)) {
            $error_data = [
                'title' => $ch_name . '发生错误，商户编号：' . $data['user_id'] . '，上游商户号：' . $data['t_mid'] . '，订单编号：' . $data['pay_order_number'] . '，错误说明：' . $error_code,
                'msg' => strip_tags($output),
                'time' => date("Y-m-d H:i:s")
            ];
        } else {
            $error_data = [
                'title' => $ch_name . '发生404错误',
                'msg' => $output,
                'time' => date("Y-m-d H:i:s")
            ];
        }

        $error_write->create($error_data);

        $this->shared->slack($error_data['title'] . ', ' . gethostname());

        if ($special != '') {
            return false;
        }

        $PayOrder_write = new PayOrderCollection('write_db');
        $PayOrder_data = ['payment_status' => '-1', 'upstream_parameter' => $data['upstream_parameter'], 'upstream_times' => $data['upstream_times']];
        $condition = ['and', 'id=:id', 'payment_status in (:status1, :status2)'];
        $params = [':id' => $data['last_id'], ':status1' => 0, ':status2' => 4];
        $PayOrder_write->updateByCondition($condition, $params, $PayOrder_data);

        $data1['message'] = '通道异常，请联系客服';
        $data1['message_code'] = '998';
        $multipay->responser->send($data1, $multipay->responser->InternalServerError());
    }

    public function error_treat($ch_name, $name, $output, $data, $i, $error_code = '')
    {
        if ($i <= TRANSFER_NUM) {
            $this->upstream_error_msg($name, $output);
            // $upstream_order_number = $this->shared->new_upstream_order_number($data['upstream_order_number']);
            // $data["upstream_order_number"] = $upstream_order_number;
            $data["upstream_times"] += 1;
            $data_msg = [
                'data' => $data,
                'msg' => false
            ];
        } else {

            $this->upstream_error_msg($name, $output);
            $this->error_msg($ch_name, $output, $data, $error_code);
            $data_msg['msg'] = true;
        }
        return $data_msg;
    }

    public function api_out($data, $url)
    {
        $Md5 = new Md5();
        $multipay = new MultipayController();
        $key = $data['user_key'];
        $data_return = [
            "message_code" => '000',
            "message" => '成功',
            $multipay->user_id => $data['user_id'],
            $multipay->pay_amount => $data['pay_amount'],
            $multipay->pay_order_number => $data['pay_order_number'],
            $multipay->my_order_number => $data['my_order_number'],
            "url" => $url,
        ];
        $sign = $Md5->md5sign($key, $data_return);
        $data_return['sign'] = $sign;
        $multipay->responser->send($data_return, $multipay->responser->OK());
    }

    public function writeOrder($order)
    {
        $fileName = ORDER_LOG . 'order.log';
        $device = fopen($fileName, "a");
        $string = "$order \r\n";
        fwrite($device, $string);
        fclose($device);
    }

    public function update_order_number($data)
    {
        $collection_write = new PayOrderCollection('write_db');
        $collection_write->getById($data['last_id'])->update(array('upstream_order_number' => $data['upstream_order_number']));
    }

    public function update_order_times($data)
    {
        $collection_write = new PayOrderCollection('write_db');
        $collection_write->getById($data['last_id'])->update(array(
            'upstream_times' => $data['upstream_times'],
            'pool_id' => $data['pool_id'],
            't_mid' => $data['t_mid'],
            't_account' => $data['t_account'],
            'judgment' => $data['strategy_index'],
        ));
    }

    public function poolUpdataTransaction($data, $type = 0)
    {
        $poolCollection_write = new PoolCollection('write_db');

        $poolRuleCollection = new PoolRuleCollection('write_db');
//        $poolmodel_set =
//            [
//                "area1" =>$data['area1'],
//                "area2" =>$data['area2']
//            ];

        $poolRule_model = $poolRuleCollection->getByCondition('pool_user_ip=:pool_user_ip AND t_mid=:t_mid AND path_id=:path_id AND user_id=:user_id',
            array('pool_user_ip' => $data["user_ip"], 't_mid' => $data["t_mid"], 'path_id' => $data["path_id"], 'user_id' => $data["user_id"]));
        $poolRule_model->increaseAttributes(array('ipcount' => 1, 'reset_count' => 1));

        $poolmodel = $poolCollection_write->getById($data['pool_id']);
        $poolmodel->increaseAttributes(array('use_money' => $data['pay_amount'], 'use_number' => 1));
        $poolCollection_write->updateByConditionIncreaseAttributes('t_mid=:t_mid AND path_id=:path_id',
            array(":t_mid" => $data["t_mid"], ":path_id" => $data["path_id"]),
            array('today_money' => $data['pay_amount'], 'today_use' => 1, 'status' => $type));
    }

    /*
     * 水池状态更改
     */
    public function poolStatusChange($pool_id, $status)
    {
        $poolCollection_write = new PoolCollection('write_db');
        $poolmodel = $poolCollection_write->getById($pool_id);
        $poolmodel->update(array('status' => $status));
    }

    /*
     * 订单水池更新
     */
    public function payOrderPoolChange($data, $pool_judgment = '', $waitTime = 1200, $status = -3)
    {
        $this->poolStatusChange($data['pool_id'], $status);
        $pool = $this->poolAddOrder($data, $pool_judgment, false, $waitTime);
        $poolChang = array(
            'pool_id' => $pool['id'],
            't_mid' => $pool['t_mid'],
            't_account' => $pool['t_account'],
            'judgment' => $pool['strategy_index'],
        );
        $data = array_merge($data, $poolChang);
        return $data;
    }


    /*
     * 中转订单ID加密
     */
    public function shorturl($url)
    {
        $url = crc32($url);
        $result = sprintf("%u", $url);
        return $this->code62($result);
    }

    public function code62($x)
    {
        $show = '';
        while ($x > 0) {
            $s = $x % 62;
            if ($s > 35) {
                $s = chr($s + 61);
            } elseif ($s > 9 && $s <= 35) {
                $s = chr($s + 55);
            }
            $show .= $s;
            $x = floor($x / 62);
        }
        return $show;
    }

    private function poolStrategy($poolCollection, $data_search, $data, $waitTime, $strategy_start)
    {
        $poolList = null;
        //設定循環次數
        $times = 7;

        for ($st = $strategy_start; $st <= $times; $st++) {
            $isGetRecords = false;
            switch ($st) {
                case -2:
                    // 成功率
                    $data_search['wait_time'] = 360;
                    $data_search['order_by'] = 'success_rate';
                    break;
                case -1:
                    // 全部條件
                    $data_search['wait_time'] = $waitTime;
                    if ($data_search['user_id'] == 'bab00027' || $data_search['user_id'] == "bab00072" || $data_search['user_id'] == "652b00056" || $data_search['user_id'] == "bab00079") {
                        $data_search["limited_time"] = true;
                    }
                    break;
                case 0:
                    // 全部條件
                    unset($data_search['area3']);
                    $data_search['wait_time'] = $waitTime;
                    if ($data_search['user_id'] == 'bab00027' || $data_search['user_id'] == "bab00072" || $data_search['user_id'] == "652b00056" || $data_search['user_id'] == "bab00079") {
                        $data_search["limited_time"] = true;
                    }
                    break;
                case 1:
                    // 地區空
                    unset($data_search["limited_time"]);
                    $data_search['area1'] = '';
                    $data_search['area2'] = '';
                    $data_search['order_by'] = 'id_desc';
                    break;
                case 2:
                    // 地區1
                    $data_search['wait_time'] = $waitTime;
                    $data_search['area1'] = $data['area1'];
                    unset($data_search['area2']);
                    break;
                case 3:
                    $data_search['wait_time'] = $waitTime;
                    $data_search['area1'] = $data['area1'];
                    unset($data_search['area2']);
                    $data_search['today_use'] = false;
                    $data_search['today_money'] = false;
                    break;
                case 4:
                    $data_search['area1'] = 'other';
                    $data_search['area2'] = 'other';
                    $data_search['wait_time'] = $waitTime;
                    $data_search['today_use'] = false;
                    $data_search['today_money'] = false;
                    break;
                case 5:
                    $this->shared->slack($data_search['swift_path'] . ': 使用随机池', SLACK_POOL_URL);
                    $isGetRecords = true;
                    $poolList = $poolCollection->getRecords(array(
                        'user_id' => $data['user_id'],
                        'path_id' => $data['path_id'],
                        'swift' => $data_search['swift'],
                        'status' => '1'
                    ), 1, 2, array(), 'last_use_time');
                    break;
                default:
                    $isGetRecords = true;
                    $poolList = null;
            }

            if ($isGetRecords) {
                if (!empty($poolList['records'])) {
                    $poolList = $poolList['records'][0];
                    $poolList['pool_id'] = $poolList['id'];
                }
            } else {
                $poolList = $poolCollection->searchRecords($data_search);
            }

            unset($data_search['wait_time']);
            unset($data_search['order_by']);

            if (!empty($poolList['pool_id'])) {
                $poolList['strategy_index'] = $st;
                break;
            }
        }
        return $poolList;
    }
//    private function poolStrategy($poolCollection, $data_search, $data, $waitTime, $strategy_start)
//    {
//        $poolList = null;
//        //設定循環次數
//        $times = 7;
//
//        for ($st = $strategy_start; $st <= $times; $st++) {
//            $isGetRecords = false;
//            switch ($st) {
//                case -1:
//                    // 成功率
//                    $data_search['wait_time'] = 360;
//                    $data_search['order_by'] = 'success_rate';
//                    break;
//                case 0:
//                    // 全部條件
//                    $data_search['wait_time'] = $waitTime;
//                    break;
//                case 1:
//                    // 地區空
//                    $data_search['area1'] = '';
//                    $data_search['area2'] = '';
//                    break;
//                case 2:
//                    // 地區1
//                    $data_search['area1'] = $data['area1'];
//                    $data_search['wait_time'] = $waitTime;
//                    unset($data_search['area2']);
//                    break;
//                case 3:
//                    $data_search['area1'] = $data['area1'];
//                    unset($data_search['area2']);
//                    $data_search['today_use'] = false;
//                    $data_search['today_money'] = false;
//                    break;
//                case 4:
//                    $isGetRecords = true;
//                    $poolList = $poolCollection->getRecords(array(
//                        'user_id' => $data['user_id'],
//                        'path_id' => $data['path_id'],
//                        'swift' => $data_search['swift'],
//                        'area1' => $data['area1'],
//                        'area2' => $data['area2'],
//                        'status' => '2'
//                    ), 1, 2, array(), 'last_use_time');
//                    break;
//                case 5:
//                    $data_search['area1'] = 'other';
//                    $data_search['area2'] = 'other';
//                    $data_search['today_use'] = false;
//                    $data_search['today_money'] = false;
//                    break;
//                case 6:
//                    $isGetRecords = true;
//                    $poolList = $poolCollection->getRecords(array(
//                        'user_id' => $data['user_id'],
//                        'path_id' => $data['path_id'],
//                        'swift' => $data_search['swift'],
//                        'area1' => 'other',
//                        'area2' => 'other',
//                        'status' => '2'
//                    ), 1, 2, array(), 'last_use_time');
//                    break;
//                case 7:
//                    $this->shared->slack($data_search['swift_path'] . ': 使用随机池', SLACK_POOL_URL);
//                    $isGetRecords = true;
//                    $poolList = $poolCollection->getRecords(array(
//                        'user_id' => $data['user_id'],
//                        'path_id' => $data['path_id'],
//                        'swift' => $data_search['swift'],
//                        'status' => '1'
//                    ), 1, 2, array(), 'last_use_time');
//                    break;
//                default:
//                    $isGetRecords = true;
//                    $poolList = null;
//            }
//
//            if ($isGetRecords) {
//                if (!empty($poolList['records'])) {
//                    $poolList = $poolList['records'][0];
//                    $poolList['pool_id'] = $poolList['id'];
//                }
//            } else {
//                $poolList = $poolCollection->searchRecords($data_search);
//            }
//
//            unset($data_search['wait_time']);
//            unset($data_search['order_by']);
//
//            if (!empty($poolList['pool_id'])) {
//                $poolList['strategy_index'] = $st;
//                break;
//            }
//        }
//        return $poolList;
//    }

    private function rank($data)
    {
        $money = $data['pay_amount'];
        $time = date("H", strtotime($data['order_time']));
        switch ($money) {
            case $money > 1999:
                $moneyInterval = array(2000, 3000);
                break;
            case $money > 999:
                $moneyInterval = array(1000, 2000);
                break;
            case $money > 299:
                $moneyInterval = array(300, 1000);
                break;
            default:
                $moneyInterval = array(1, 300);
                break;
        }
        switch ($time) {
            case $time < 8:
                $timeInterval = array("00:00:00", "07:59:59");
                break;
            case $time < 16:
                $timeInterval = array("08:00:00", "15:59:59");
                break;
            default:
                $timeInterval = array("16:00:00", "23:59:59");
                break;
        }

        return array_merge($moneyInterval, $timeInterval);
    }

}

?>