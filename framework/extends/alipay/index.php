<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include("AopSdk.php");

$aop = new AopClient();
$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
$aop->appId = '2018110661976912';
$aop->rsaPrivateKey = 'MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQCnsu0wn61YL3Th5y9SPVNHURi0XwpKUGD6eSZHeiuZ9qjOmu79aQ39XsB4DoFeZ7iaoVnvMrZwtPTvxknZg68RpeaXnCWqkx30W26gW+hVZZB57y3UbZkQDl4xy9b/r7kJdMzo0u6gncgdnUudSUTroTLfZh53ch67z6EqUX9Bj9od3WQnckaYEnoRLEwxKjBLy8M7RgMp3CimWLkp86yRjO6QqdQywAnnvX0mVOFf01Gs/AB/3d178TE6PtYVSRaqL5U0ppXNH28KK0sofIpKjVOU+sWBHVuEVHRUkspTjvFv2mOc9X+nsbwpZWFj4QEHOa2iQeiOgUGVnpC/8msLAgMBAAECggEAV4Mv8+ff9d0OCbUzJJ+MDfNsCPRv0kgP06XVLAe9KSNnBCol/WgNPONtXTl0mWdXFpqM7B5yxm4oQ9geQbxOZ89Dfmql3VXYk+QC3vwXSjkuI/OE3w4yigZ1cVcGY3e4AA9Lv1QT4w1zmMC07OeHZ88/VQVdcMfE8g1v9T2CQxuOVY6ReENfaavsm0l3hVCZphFbd8omMazVRMRacIn+x5e85JnVCIWxcpwaNedUQYMKK5WmQKdBanfGvSsp8WbLqc3krOOIkwVH9NqbpsHpttkwvBOAjuerYBy3vX0UfDF4IR9uGTy33TXEl/PqFa7L6GJxaXrsNJJ51Yne1BfKwQKBgQDTBRnCzS5qiIBrfcRzs+8q6GQPsMatjqsoX/hCNaxM35tg4EJagY5yH8062Oel9D8lIGLCTdJctbcNELlxZbjxAinHzz0BkAUGQGZFUFaM2hyKfTMNvpGKS6IxPwK7h8+nZAtvSXWSU7gmJANf5+IObNhCn7CXL8LexQIiHfbE7QKBgQDLceSxaPXGSRfZOdXFGKBKgNhUcCqsxG2cUXFq4Y/ls5JOTONcc50RdglvBSLmre86gyNwczdhvfRPAF3/NbIQGA1b5Z7VhaM4RFS9BC5AFVYzWIq44TRe91z9XSWSK3Skgo9N91GRwJ7Zhrn1b5fv2sRkJ2xT1D583SilQuYo1wKBgQCspu614NTKW1bfG+7BUAYuUCeWYueblzBY/3SLD4ki+I0TjUkc7gWTQIvVSyT1Nkr34HCNU8j7C75ylS11J2pS3pc6oUfj4GcL/2Lt8VZvNgHGGbvM0hAYW9ufeVOOBgeTiJqGek8U4yS3KB4OuRXPAaVLlYaRnIVPaVdefK+r3QKBgQCxBj+a59vEV+G6kQqj4BPKAGc8wgVAJAPEm1F3USJnG2PZYioMTkWD5hO7WNrPotWhMm7p8Ddmg2VMQOOJqG1yd5tYNWuKHCi0UzDw7+xWsro5H3hF+yAY6mEtzZldoRZz928+xk9h5hvS59pz6FBq0w9EntEx+GMPP1mYw6eGLQKBgQDHTdXEkpZOK07Oe5auZ4ny+19BKev0bA2BIcTYKZCueAwR6wTK/A8HBC+sEzexji9vguemcEDwxZLuWNW+wOx1dt0lFgjdtBvPIsY6vRU4VsS7pepBmdYMOOg2rWHO1d7l+f+abpH/gFH5vMz5PCdrSInl95T0F4xTge966CA4bw==';
// $aop->rsaPrivateKeyFilePath = 'test.pem';
// $aop->alipayrsaPublicKey='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAumUO5WZWzM9cTtfvAJti8a+4Cmr5hK+pAgHsfVAthIW8KDQEr4mg7ifYzNtyCxby9rAJakO1mzIe6L9+r1UcBEgjn+L9i1u2BN7Gng9uJ02yoZ1UV4TlG3ziDeSkOfHcs8U4pDR/bUBh/81oX0zvYdxU7MWLC9ib60aHHXGabPVTMSqopcg04RqycuLbFGx8TlNoXv0PZEHqfHHptXpKL1MnNcncbUHOddXOGvmKKkXVLzSvS5RwBPUNsq5z2bUtZrDyflPeEHnf7hIE/uKIp7Fr/doN8jZ2qdzxtgmic6LRTD2kTqRi6PhreU34+E6ZeuHNddHQi1kcCh05IsxR6QIDAQAB';
$aop->alipayrsaPublicKey='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAumUO5WZWzM9cTtfvAJti8a+4Cmr5hK+pAgHsfVAthIW8KDQEr4mg7ifYzNtyCxby9rAJakO1mzIe6L9+r1UcBEgjn+L9i1u2BN7Gng9uJ02yoZ1UV4TlG3ziDeSkOfHcs8U4pDR/bUBh/81oX0zvYdxU7MWLC9ib60aHHXGabPVTMSqopcg04RqycuLbFGx8TlNoXv0PZEHqfHHptXpKL1MnNcncbUHOddXOGvmKKkXVLzSvS5RwBPUNsq5z2bUtZrDyflPeEHnf7hIE/uKIp7Fr/doN8jZ2qdzxtgmic6LRTD2kTqRi6PhreU34+E6ZeuHNddHQi1kcCh05IsxR6QIDAQAB';

$aop->apiVersion = '1.0';
$aop->signType = 'RSA2';
$aop->postCharset='UTF-8';
$aop->format='json';


//$request = new AlipayTradeAppPayRequest();
//
//$bizcontent = "{\"body\":\"我是测试数据\","
//                . "\"subject\": \"App支付测试\","
//                . "\"out_trade_no\": \"20170125test01\","
//                . "\"timeout_express\": \"30m\","
//                . "\"total_amount\": \"0.01\","
//                . "\"product_code\":\"QUICK_MSECURITY_PAY\""
//                . "}";
//$request->setNotifyUrl("商户外网可以访问的异步地址");
//$request->setBizContent($bizcontent);
//$response = $aop->sdkExecute($request);
//echo htmlspecialchars($response);

$request = new AlipaySystemOauthTokenRequest ();
$request->setGrantType("authorization_code");
$request->setCode("a984f8c5fc2c4c2c894350dbbce0XX51");
$request->setRefreshToken("201208134b203fe6c11548bcabd8da5bb087a83b");
$result = $aop->execute ( $request);
var_dump($result);

/*
$request = new AlipayTradeWapPayRequest();
$request->bizContent = "{" .
"    \"primary_industry_name\":\"IT科技/IT软件与服务\"," .
"    \"primary_industry_code\":\"10001/20102\"," .
"    \"secondary_industry_code\":\"10001/20102\"," .
"    \"secondary_industry_name\":\"IT科技/IT软件与服务\"" .
" }";
$result = $aop->pageExecute($request);



var_dump($result);
$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
$resultCode = $result->$responseNode->code;
var_dump($resultCode);
if(!empty($resultCode)&&$resultCode == 10000){
    echo "成功qq";
} else {
    echo "失败qq";
}*/
https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2018110661976912&scope=auth_base&redirect_uri=http://apitts.jinrongbook.com:9237/jinrong/callback