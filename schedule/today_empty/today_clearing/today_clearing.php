<?php
$db =  new PDO('mysql:host=35.229.132.173;dbname=wallet','starpay','root@root#2');
$db->query( "SET NAMES 'UTF8'" );

$query_today_clearing = "SELECT user_id,r.path,SUM(pay_actualamount) pay_actualamount,(SUM(IF(pay_actualamount != 0,pay_actualamount,0))*(100-ANY_VALUE(p.settlement_rate))/100) today_clearing
FROM recharge AS r LEFT JOIN path AS p ON p.path=r.path  WHERE TO_DAYS(NOW())-TO_DAYS(payment_time)=1 AND cash_status=0 AND recharge_status=1 GROUP BY user_id,path";
$record_today_clearing = query($db,$query_today_clearing);
if(empty($record_today_clearing)){
    $txt = "今日无资料需更新";
    log_w($txt,'success');
    exit();
}
$update_recharge = "UPDATE recharge SET recharge_status=2 WHERE TO_DAYS(NOW())-TO_DAYS(payment_time)=1 AND cash_status=0 AND recharge_status=1";
$update_recharge_status = $db->exec($update_recharge);
if(!$update_recharge_status){
    $txt = "充值状态更新失败";
    log_w($txt,'fail');
    exit();
}else {
    $txt = "充值状态更新成功";
    log_w($txt,'success');
}

$record_today_clearing = $record_today_clearing[0];
foreach ($record_today_clearing as $key => $val){
    $today_clearing = round($val['today_clearing'],3);
    $user_id = $val['user_id'];
    $path = $val['path'];
    if(!empty($today_clearing)){
        $update_amount = "UPDATE amount SET usable_money=usable_money+$today_clearing, no_usable_money=no_usable_money-$today_clearing WHERE `user_id` = '$user_id' AND `path` = '$path'";
        $update_amount_status = $db->exec($update_amount);
        if(!$update_amount_status){
            $txt = "钱包结算更新失败，商户编号：".$user_id."，通道：".$path."，应结算金额：".$today_clearing;
            log_w($txt,'fail');
        }else{
            $txt = "钱包结算更新成功，商户编号：".$user_id."，通道：".$path."，应结算金额：".$today_clearing;
            log_w($txt,'success');
        }
    }
}

function log_w($txt,$status){
    $date = date("Y-m-d");
    $myfile = fopen(dirname(dirname(dirname(dirname(__FILE__)))) ."/log/schedule_logs/today_clearing_logs/$status/today_clearing_$date.log", "a+") or die("Unable to open file!");
    $now =  date("Y-m-d h:i:sa");
    $string = "[$now] $txt \r\n";
    fwrite($myfile, $string);
    fclose($myfile);
}

function query($db,$sql){
    $statement = $db->query($sql);
    $record = array();
    if( $row = $statement->fetchAll( PDO::FETCH_ASSOC ) )
    {
        $record[] = $row;
    }
    return $record;
}
?>