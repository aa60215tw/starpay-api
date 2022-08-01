<?php
require_once(FRAMEWORK_PATH . 'controllers/MultipayController.php');
require_once(FRAMEWORK_PATH . 'extends/WalletUpdate/Wallet.php');
require_once(FRAMEWORK_PATH . 'extends/Qrcode/qrlib.php');
require_once(FRAMEWORK_PATH . 'extends/Signature/Md5.php');
require_once(FRAMEWORK_PATH . 'extends/Signature/Rsa.php');
require_once(FRAMEWORK_PATH . 'extends/Signature/Des.php');
require_once(FRAMEWORK_PATH . 'extends/CashFlow/CashFlowProvider.php');

class Shared
{

    public function search_key($upstream_order_number)
    {
        if (is_numeric(substr($upstream_order_number, -2))) {
            $upstream_order_number = substr($upstream_order_number, 0, -2);
        }

        $order_write = new PayOrderCollection('write_db');
        $order_records = $order_write->getRecordByCondition('upstream_order_number=:upstream_order_number AND payment_status IN (0,4, -1)'
            ,array('upstream_order_number' => $upstream_order_number));
        if(empty($order_records))
            return "order_not_find";

        $pool = new PoolCollection();

        $pool_records = $pool->getRecordById($order_records['pool_id']);
        if(empty($pool_records))
            return "pool_not_find";

        $data = array_merge($pool_records,$order_records);
        return $data;
    }

    public function new_upstream_order_number($data, $id=null)
    {
        $booking= base_convert($data,32,36);
        // $rand = $this->bab_rand();
        $rand = '';
        if (!empty($id)) {
            $rand .= substr($id, -3);
        } else {
            $rand = $this->bab_rand();
        }
        $upstream_order_number = date("YmdHis").$rand.$booking;
        return $upstream_order_number;
    }

    public function concat_upstream_order_number($upstream_order_number, $upstream_times)
    {
        if (empty($upstream_times)) {
            return $upstream_order_number;
        }
        return $upstream_order_number . substr(str_pad($upstream_times, 2, "0", STR_PAD_LEFT), -2);
    }

    public function cash($cash,$image='no')
    {
        if($image == 'no')
            $url = $this->qr_code($cash["url"]);
        else
            $url = $cash["url"];
        ob_clean();
        echo "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head>正在跳转 ...<body onLoad=\"document.ShowForm.submit();\">";
        echo "<form name=\"ShowForm\" method=\"post\" action=\"".CASH_URL."\" target=\"_self\">";
        echo "<input type=\"hidden\" name=\"ordernumber\" value=\"".$cash["ordernumber"]."\"/>";
        echo "<input type=\"hidden\" name=\"money\" value=\"".((float)$cash["money"]*1)."\"/>";
        echo "<input type=\"hidden\" name=\"createTime\" value=\"".date("Y-m-d H:i:s")."\"/>";
        echo "<input type=\"hidden\" name=\"banktype\" value=\"".$cash["banktype"]."\"/>";
        echo "<input type=\"hidden\" name=\"url\" value=\"".$url."\"/>";
        echo "</form></body></html>";
        ob_flush();
        exit();
    }

    function qr_code($data) {
        $PNG_TEMP_DIR = ROOT.'public/qrcode/';
        $errorCorrectionLevel = 'H';
        $matrixPointSize = 5;

        $filename = $PNG_TEMP_DIR.'test'.md5($data.'|'.$errorCorrectionLevel.'|'.$matrixPointSize).'.png';
        QRcode::png($data, $filename, $errorCorrectionLevel, $matrixPointSize, 2);

        if($fp = fopen($filename,"rb", 0))
        {
            $gambar = fread($fp,filesize($filename));
            fclose($fp);
            $base64 = chunk_split(base64_encode($gambar));
            return $base64;
        }
    }

    function call_curl($order_list,$url=CALL_URL) {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $order_list);
        curl_setopt($ch,CURLOPT_TIMEOUT,10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        curl_close($ch);
    }

    /*
     * *取字串亂數
     */
    function randtext($length) {
        $password_len = $length;
        $password = '';
        $word = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = strlen($word);
        for ($i = 0; $i < $password_len; $i++) {
            $password .= $word[rand() % $len];
        }
        return $password;
    }


    /**
     * 取IP實際位置
     * @param $ip
     * @return string
     */
    public function getipadd($ip)
    {
        $result = $this->_getip_taobao($ip);
        if (!$result) {
            $result = $this->_getip_fileDB($ip);
        }
        if (!$result) {
            $result = $this->_getip_aliyun($ip);
        }
        return $result;
    }

    public function _getip_taobao($ip)
    {
        try {
            $url = "http://ip.taobao.com/service/getIpInfo.php?ip=$ip";
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_FAILONERROR, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT, 5);
            $_result = curl_exec($curl);
            curl_close($curl);
            $res = json_decode($_result,true);
            if($res['code'] =='0'){
                return $res['data'];
            }

        } catch (\Exception $e) {

        } catch (\Throwable $t) {

        }
        return false;
    }

    public function _getip_fileDB($ip)
    {
        try {
            $city = new ipip\db\City(IP_OFFLINE_DB);
            $result = $city->findMap($ip, 'CN');
            if ($result) {
                $result['region'] = $result['region_name'];
                $result['city'] = $result['city_name'];
            }
            return $result;
        } catch (\Exception $e) {

        } catch (\Throwable $t) {

        }
        return false;
    }
    public function _getip_aliyun($ip)
    {
        try {
            $host = "https://dm-81.data.aliyun.com";
            $path = "/rest/160601/ip/getIpInfo.json";
            $method = "GET";
            $y=rand(0,2);
            if($y==0){
                $appcode = "05a3542cbc5945e9b153bc8c61897001";
            }
            else if($y==1){
                $appcode = "2eb776a6caa440c58aaa2807fc27bea8";
            }
            else if($y==2){
                $appcode = "56b3c3e17e184b22bd40ccced499dcf9";
            }
            $headers = array();
            array_push($headers, "Authorization:APPCODE " . $appcode);
            $querys = "ip=".$ip;
            $bodys = "";
            $url = $host . $path . "?" . $querys;
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_FAILONERROR, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT, 5);
            if (1 == strpos("$".$host, "https://"))
            {
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            }
            $_result = curl_exec($curl);
            curl_close($curl);
            $res = json_decode($_result,true);
            if($res['code'] =='0'){
                return $res['data'];
            }
        } catch (\Exception $e) {

        } catch (\Throwable $t) {

        }
        return false;
    }

    function curl($url, $data, $ch_name, $name, $status='post', $header=array(), $ssl='off', $time_out = 6) {
        $weburl = 'http://happytopay.jishenghe168.com/';
        if(empty($header)){
            $header = array(
                'CLIENT-IP:35.234.34.97',
                'X-FORWARDED-FOR:35.234.34.97',
            );
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36");
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER,$weburl);
        //curl_setopt($ch, CURLOPT_PROXY, PROXY_SERVER);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $time_out);
        curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);
        if($ssl == 'on') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        }
        if($status == 'post'){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);
        $path_up_log = new LoggerHelper('path_up_log', PATH_REQUEST_LOG);
        $msg = $ch_name."-请求时间:".$info['total_time'];
        $path_up_log->warn($msg);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE); //获取url响应
        if($httpCode == 404 || $httpCode == 403) {
            $CashFlow = new CashFlowProvider;
            $CashFlow->upstream_error_msg($name, "404网页错误");
            $CashFlow->error_msg($ch_name,$output);
        }
        curl_close($ch);
        return $output;
    }


    /*
    * *slack 通知
    */
    function slack($txt , $url = SLACK_URL){
        $text = array("text" => $txt);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($text));
        curl_exec($ch);
        curl_close($ch);
    }

    /*
    * *取IP
    */
    function get_ip(){
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /*
    * *将qrcode图片转回网址
    */
    function imgToUrl($img){
        $QRCodeReader = new Libern\QRCodeReader\QRCodeReader();
        $qrcode_text = $QRCodeReader->decode("$img");
        return $qrcode_text;
    }

    /*
    * *更改挡案资料
    */
    function changeFile($filename,$key,$data)
    {
        $array = file($filename);
        for($i = 0; $i < count($array); $i++)
        {
            if($domain = strstr($array[$i], $key))
            {
                $array[$i] = $data;
                break;
            }
        }
        $content = implode('', $array);
        $fd = fopen($filename, "w");
        fwrite($fd, $content);
        fclose($fd);
    }

    /*
    * *中转加密
    */
    function code62($x) {
        $show = '';
        while($x > 0) {
            $s = $x % 62;
            if ($s > 35) {
                $s = chr($s+61);
            } elseif ($s > 9 && $s <=35) {
                $s = chr($s + 55);
            }
            $show .= $s;
            $x = floor($x/62);
        }
        return $show;
    }

    /*
    * *中转加密
    */
    function shorturl($url) {
        $url = crc32($url);
        $result = sprintf("%u", $url);
        return $this->code62($result);
    }

    /**
     * 更好的随机方法
     */
    public function bab_rand()
    {
        $rand = mt_rand(1, 999);
        return str_pad($rand, 3, "0", STR_PAD_LEFT);
    }
}
?>