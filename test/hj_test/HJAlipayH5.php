<?php
require_once dirname(dirname(dirname(__FILE__))).'/configs/sys.config.inc.php';
require_once FRAMEWORK_PATH . 'extends/Shared.php';

Class HJAlipayH5
{
    protected $shared;
    protected $url;
    protected $zhuan_url;
    protected $callback_url;
    protected $partner;
    protected $paymoney;
    protected $ordernumber;
    protected $db;

    protected $alipay_app_id;
    protected $auth_code;
    protected $alipay_user_id;

    function __construct($ordernumber = '')
    {
        $this->shared = new Shared();
        $this->url = "http://127.0.0.1:8081/pay/unifiedpay";
        $this->zhuan_url = "http://127.0.0.1:8081/zong/zhuan.php";
        $this->callback_url = "http://127.0.0.1:8081/callback/BabJoinpayback.php";
        $this->partner = "652b00056";
        $this->paymoney = 0.2;

        $this->ordernumber = (empty($ordernumber)) ? rand(1,99999999).$this->partner : $ordernumber;

        $this->alipay_app_id = '2018110661976912';
        $this->auth_code = '7446d393340c44898fb8680186b5QE51';
        $this->alipay_user_id = '2088132133699511';


        $this->db = new PDO(
            sprintf('mysql:host=%s;dbname=%s', READ_DB_HOST, READ_DB_NAME),
            READ_DB_LOGIN_USER,
            READ_DB_LOGIN_PASSWORD
        );
        $this->db->query( "SET NAMES 'UTF8'" );

    }

    public function generateZhuanUrl($zhuan_url)
    {
        $url = sprintf(
            '%s&alipay_user_id=%s&auth_code=%s&alipay_app_id=%s',
            $zhuan_url,
            $this->alipay_user_id,
            $this->auth_code,
            $this->alipay_app_id
        );
        return $url;
    }
    /**
     * 模拟使用者连 zong/Zhuan
     */
    public function emulateZhuan($zhuan_url)
    {
        $output = trim($this->curl($zhuan_url . '&asyn=1', '', 'post', 30));
        return $output;
    }

    /**
     * 从 url 中读取 r
     */
    public function fetchR($url)
    {
        $parts = parse_url($url);
        parse_str($parts['query'], $query);
        if (empty($query['r'])) {
            $this->fail_print('读取 r 失败');
        }
        return $query['r'];
    }

    /**
     * 从 alipays:// 中读取 url
     */
    public function fetchZhuanUrl($string)
    {
        preg_match('/&url=(.*)/', $string, $output_array);
        if (empty($output_array[1])) {
            $this->fail_print('中转 url 抓取失败');
        }
        return $output_array[1];
    }

    /**
     * 一开始支付 addorder
     */
    public function pay()
    {
        $callbackurl = "http://35.194.228.95";
        $hrefbackurl = "http://35.194.228.95/callback.php";
        $banktype = "ALIPAY_APP";
        $client_key = "022DAFFCA8E0CCABCD5C01341BB77EB2";
        $api_type = "1";
        $currency ="CNY";

        //支付寶APP
        $jsapiapp = array(
            "partner" => $this->partner,
            "banktype" => $banktype,
            "paymoney" => $this->paymoney,
            "ordernumber" => $this->ordernumber,
            "callbackurl"=> $callbackurl,
            "currency"   => $currency
        );
        ksort($jsapiapp);
        $md5str = "";
        foreach ($jsapiapp as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . $client_key));
        $jsapiapp["hrefbackur"]=$hrefbackurl;
        $jsapiapp["api_type"]=$api_type;
        $jsapiapp["attach"]="客户自定义字段";
        $jsapiapp["sign"] = $sign;
        $jsapi = http_build_query($jsapiapp);

        $output = trim($this->curl($this->url, $jsapi));
        return $output;
    }

    /**
     * 回调 callback
     */
    public function callback($param)
    {
        $jsapi = http_build_query($param);
        $output = $this->curl($this->callback_url . '?' . $jsapi, [], 'get', 30);
        return $output;
    }

    /**
     * 检查回传资料
     */
    public function verify_paydata($data)
    {
        if ($data['partner'] != $this->partner) {
            $this->fail_print('paydata partner 异常');
        }
        if ($data['paymoney'] != $this->paymoney) {
            $this->fail_print('paydata paymoney 异常');
        }
        if ($data['ordernumber'] != $this->ordernumber) {
            $this->fail_print('paydata ordernumber 异常');
        }
    }

    public function fail_print($data)
    {
        print_r(PHP_EOL);
        print_r($data);
        exit();
    }

    public function db_get_by_ordernum()
    {
        $sql = "SELECT * FROM pay_order WHERE pay_order_number = '" . $this->ordernumber . "' LIMIT 1";
        $data = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $data;
    }

    public function db_get_by_id($id)
    {
        $sql = "SELECT * FROM pay_order WHERE id = '" . $id . "' LIMIT 1";
        $data = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $data;
    }

    public function db_get_by_upstream($upstream_order_number)
    {
        $sql = "SELECT * FROM pay_order WHERE upstream_order_number = '" . $upstream_order_number . "' LIMIT 1";
        $data = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $data;
    }

    public function db_pool_by_taccount($t_account)
    {
        $sql = "SELECT * FROM pool WHERE t_account = '" . $t_account . "' LIMIT 1";
        $data = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $data;
    }

    public function md5sign($data, $signKey)
    {
        ksort($data);
        $md5str = "";
        foreach ($data as $k=>$v){
            if("" != $v && "hmac" != $k){
                $md5str.=$v;
            }
        }
        $md5str = $md5str.$signKey;
        $sign=md5($md5str);
        return $sign;
    }

    function curl($url, $data, $status='post', $time_out = 6) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36");
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $time_out);
        curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);

        if($status == 'post'){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);

        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $output;
    }
}
