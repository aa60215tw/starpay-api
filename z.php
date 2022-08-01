<?php
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(0);
		include("AopSdk.php");
        date_default_timezone_set("Etc/GMT-8");

         if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
            $result=file_get_contents('php://input');

            file_put_contents("callback.txt",date("Y-m-d H:i:s")." ip=".$ip . "  ".$result."GET".json_encode($_GET,320)."POST".json_encode($_POST,320)."\r\n", FILE_APPEND);
			

			$url = "http://4e6684af.ngrok.io/starpay-api/zong/zhuan.php?r=".$_GET['r']."&auth_code=".$_GET['auth_code'];
			header("location:$url");
            //echo "200";
			echo "success";exit;
			//echo "SUCCESS";exit;
			//echo json_encode(array('msg'=>'success'),320);exit;


?>
