<?php

$ordernumber = isset($_POST['ordernumber']) ?  $_POST['ordernumber'] : '';
$money = isset($_POST['money']) ?  $_POST['money'] : '0';
$url = isset($_POST['url']) ?  $_POST['url'] : '';
?>
<html xmlns="http://www.w3.org/1999/xhtml"><head>
    <title>欢迎使用百八支付</title>

    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="public/images/favicon.ico" type="image/x-icon">
    <link rel="icon" href="public/images/favicon.ico" type="image/x-icon">
    <link rel="bookmark" href="public/images/favicon.ico">
    <link rel="stylesheet" href="public/css/aos.css">
    <link rel="stylesheet" href="public/css/styles.css">
    <link type="text/css" rel="stylesheet" href="public/css/style.css">
    <script src="public/js/jquery-1.8.0.js" type="text/javascript"></script>
    <script src="public/js/highlight.min.js"></script>
    <script src="public/js/aos.js"></script>
</head>
<body aos-easing="ease-out-back" aos-duration="1000" aos-delay="0">

    <div class="loginbg">
        <div class="warper">
            <div class="wd900 mt50">
                <div class="wd50pc fl aos-init aos-animate" aos="fade-right">
                    <div class="head"><p class="txc"><img src="public/images/logo.png" alt="百八支付"></p></div>
                    <div class="orderNum mt20 txc"><span class="sgray">订单号：</span><span class="fsbld fs16"><?=$ordernumber?></span></div>
                    <div class="mt10 txc"><span class="sgray">支付金额：</span><span class="fs55 red"><?=sprintf("%01.2f",$money)?></span><i>元</i></div>
                    <div class="mt20 txr">

                        <img src="public/images/tips.png" width="258">
                    </div>
                </div>
                <div class="wd50pc fr">
                    <div class="mbbg aos-init aos-animate" aos="fade-left">
                        <div class="mt220">
                            <img src="data:image/bmp;base64,<?=$url?>" style="width: 188px; height: 188px;">
                        </div>
                    </div>
                </div>
                <div class="clearfix"></div>
            </div>
        </div>
    </div>