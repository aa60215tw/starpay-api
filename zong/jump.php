<?php
require_once('../configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');

if(empty($_GET)){
    exit;
}
$r = $_GET['r'];

$jump_log = new LoggerHelper('jump_log', IP_LOG);
$msg = "R = ". base64_decode($r);
$jump_log->warn($msg);

header("location:" . getUrl($r));
exit;

function getUrl($r)
{
    $appIdFile = glob('*.xxx');
    //$appId = ['2019042964342367'];
    $appId = ['2018112362303497', '2018120462439121'];
    if(!empty($appIdFile)){
        foreach ($appIdFile as $k => $v){
            $newAppId = substr($v, 0, -4);
            $key=array_search($newAppId ,$appId);
            array_splice($appId,$key,1);

        }
    }

    switch ($appId[array_rand($appId,1)]){
//        case '2019042964342367':
//            $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2019042964342367&scope=auth_base&redirect_uri=http://oauth.riskgo.club/alipay/authorize.html?appid=110&redirecturi="
//                . URL."zong/zhuan.php?r=". $r;
//            break;
        case '2018112362303497':
            $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2018112362303497&scope=auth_base&redirect_uri=".URL."zong/zhuan.php?r=". $r;
            break;
        case '2018120462439121':
            $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2018120462439121&scope=auth_base&redirect_uri=".URL."zong/zhuan.php?r=". $r;
            break;
        case '2018121262550151':
            $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2018121262550151&scope=auth_base&redirect_uri=http://open.bondepay.com/jump/z.php?r=". $r;
            break;
        case '2018112662286976':
            $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2018112662286976&scope=auth_base&redirect_uri=http://open.bondepay.com/jump/z.php?r=". $r;
            break;
        default:
            $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2018112362303497&scope=auth_base&redirect_uri=".URL."zong/zhuan.php?r=". $r;
    }
    return $url;
}