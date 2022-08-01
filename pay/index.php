<?php

require_once('../configs/sys.config.inc.php');

try
{
	$PushSynature = new Synature(array(
		'SysRoot' => ROOT,
		'frameworkRoot' => FRAMEWORK_PATH,
        'logPath' => LOG_PATH,
		'router' => array(
			'ctrlRoot' => FRAMEWORK_PATH . 'controllers',
			'patterns' => array(
                array( "POST: /unifiedpay", "MultipayController", "unifiedpay()"),
                array( "POST: /orderquery", "MultipayController", "orderquery()"),
                ////API用

                array( "POST: /search_pay", "SelfApiController", "search_pay()"),
                array( "POST: /search_easy","SelfApiController", "search_easy()"),
                array( "POST: /call_status_push","SelfApiController", "call_status_push()"),
                array( "POST: /merchant_call","SelfApiController", "merchantCall()"),
                array( "POST: /obtp_send","SelfApiController", "obtpSend()"),
                array( "POST: /zong_zhuan","SelfApiController", "zongZhuan()"),
                array( "POST: /alipay_jsapi_result","SelfApiController", "alipay_jsapi_result()"),

				        //Wallet
                array( "POST: /wallet_balance", "WalletpayController", "wallet_balance()"),
                array( "POST: /cash","WalletpayController", "cash()"),
                array( "POST: /rechargelist","WalletpayController", "rechargelist()"),
                array( "POST: /cashlist","WalletpayController", "cashlist()"),

            ),
			'default' => array( "DefaultController", "getNotFound" )
		)
	));

} catch(Exception $e) {
    error_log($e->getMessage());
	// echo $e->getMessage();
} catch (Throwable $t) {
    error_log($t->getMessage());
	// echo $t->getMessage();
}
