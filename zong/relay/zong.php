<?php
error_reporting(0);
date_default_timezone_set("Etc/GMT-8");
try{

    $ip = ip();
    $input = $_GET;
    $input_original = $input;
    $status = 'get';
    if(empty($input)) {
        $input = file_get_contents( 'php://input' );
        $input_original = $input;
        $status = 'post';
        if(json_decode( $input, true )){
            $data = json_decode( $input, true );
        }else if(!empty($_POST)){
            $input = $_POST;
        }else{
            parse_str($input, $input);
        }
    }

    file_put_contents("log/".date("Y-m-d").".txt", '||'.$ip.'||'."传入资料-json:".json_encode($input,320)."传入资料-原始:".$input_original."\r\n", FILE_APPEND);

    if(empty($input))
        exit;

    $url = getHeader('Url');
    $output = curl($url, $input_original, $status);
    file_put_contents("log/".date("Y-m-d")."output.txt", '||'.$ip.'||'."出参:".$output."入参:".$input_original."\r\n", FILE_APPEND);

    exit($output);
}catch (Exception $e){
    file_put_contents("log/".date("Y-m-d")."error.txt", '||'.$ip.'||'."传入资料-json:".json_encode($input,320)."传入资料-原始:".$input_original."错误内容:".$e."\r\n", FILE_APPEND);
}


function ip(){
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function getHeader($name)
{
    $header = apache_request_headers();
    return $header[$name];
}

function curl($url, $data, $status = 'post'){
    $weburl = 'http://happytopay.jishenghe168.com/';
    $header_ip = array(
        'CLIENT-IP:35.194.228.20',
        'X-FORWARDED-FOR:35.194.228.20',
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36");
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER,$weburl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header_ip);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    if($status == 'post'){
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}