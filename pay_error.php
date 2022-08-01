<?php
$message = isset($_POST['message']) ?  $_POST['message'] : '';
$message_code = isset($_POST['message_code']) ?  $_POST['message_code'] : '';
?>
<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<!--    <title>欢迎使用百八支付</title>-->
    
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

<!--                    <div class="head"><p class="txc"><img src="public/images/logo.png" alt="百八支付"></p></div>-->
                    <div class="orderNum mt20 txc"><span class="sgray">错误码：</span><span class="fsbld fs16"><?php echo $message_code ?></span></div>
				    <div class="errorMesg mt30 txc"><span class="sgray">错误讯息：</span><span class="fs16 red"><?php echo $message ?></span></div>

            </div>
        </div>
    </div>
</body>
</html>