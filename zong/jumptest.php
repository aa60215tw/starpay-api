<?php
require_once('../configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'extends/alipay/AlipayH5.php');
if (empty($_GET['auth_code'])) {
    exit();
}

$alipayH5 = new AlipayH5($_GET['app_id']);
$alipayH5Data = $alipayH5->getUserId($_GET['auth_code'], '');
if($alipayH5Data['status']){
    echo "<a style='font-size:500%'>您的支付宝收款ID为<br>". $alipayH5Data['user_id'] ."</a>";
    exit;
}
