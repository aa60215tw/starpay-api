<?php
require_once('configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');

error_reporting(0);
if(empty($_SERVER["HTTP_REFERER"]) || empty($_POST['data'])){
    @header("http/1.1 404 not found");
    @header("status: 404 not found");
    exit;
}

$des = new Des();
$data = $_POST['data'];
$data = $des->desing3des(AUTH_KEY,$data);
$data = json_decode($data,true);
?>

<html>
<head>
    <meta charset="utf-8">
    <title>快捷收银台</title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="">
    <meta name="keywords" content="">
    <meta name="viewport" content="initial-scale=1.0, width=device-width, maximum-scale=1.0">
    <meta content="telephone=no" name="format-detection">
    <link rel="shortcut icon" href="public/images/favicon.ico" type="image/x-icon">
    <link rel="icon" href="public/images/favicon.ico" type="image/x-icon">
    <link rel="bookmark" href="public/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="public/css/tenpayStyle.css">

    <style>
        body{
            background-color:#FCFCFC;
        }
    </style>

</head>

<body>
<header class="bank-header">
    <div class="header">
        <div class="logo"><img src="public/images/logo.png"></div>
        <div class="head_title"><h1>快捷收银台</h1></div>
    </div>
</header>

<div class="content">
    <div class="payment-order">
        <div class="order-info">
            <div class="order-num">订单号:<?php echo $data['pay_order_number'] ?></div>
            <div class="order-time">订单时间:<?php echo date("YmdHis") ?></div>
        </div>
        <p>请您在提交订单后<b><span class="txt-orange">10分钟</span></b>完成支付。</p>
        <p class="txt-b"><b>应付金额：<span class="txt-orange"><?php echo $data['pay_amount'] ?></span>元</b></p>
    </div>
    <div class="bank-bg">
        <div class="bank-title"><h2>快捷支付</h2></div>
        <form class="form-inline" name="showForm" method="post" target="_self" action="pay/obtp_send">
            <div class="bank-warp extra-space">
                <div class="bank-form">
                    <p class="txt-a txt-orange">请输入银行卡卡号</p>
                    <div class="col col-40">
                        <label for="">卡号：</label>
                        <input type="text" name="bank_name" id="bank_name">
                    </div>
                    <?php
                    echo '<input type="hidden" name="data" value="' . $_POST['data'] . '">'
                    ?>
                </div>
                <div class="bank-form-btn"><a href="javascript:;" onclick="onclick_bank()">到网上银行支付</a></div>
            </div>
        </form>
    </div>
</div>
</body>

</html>
<script>
    document.getElementById('bank_name').focus();
    function onclick_bank()
    {
        var bank_name = document.getElementById('bank_name').value;
        if (bank_name == '') {
            alert('请输入卡号');
            document.getElementById('bank_name').focus();
        } else if (isNaN(bank_name)) {
            alert('请输入正确卡号');
            document.getElementById('bank_name').focus();
        } else {
            document.showForm.submit();
        }
    }
</script>
