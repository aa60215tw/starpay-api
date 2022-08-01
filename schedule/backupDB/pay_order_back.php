<?php
require_once(dirname(dirname(__FILE__)) . '/../configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . '/controllers/MultipayController.php');
require_once(FRAMEWORK_PATH . 'collections/PayOrderBackupCollection.php');

$log_call = new LoggerHelper('backup_call', PAY_ORDER_BACKUP);
$log_fail = new LoggerHelper('backup_fail', PAY_ORDER_BACKUP);
$log_success= new LoggerHelper('backup_success', PAY_ORDER_BACKUP);

$call = "pay_order备份呼叫成功";
$log_call->warn($call);
$order_backup_write = new PayOrderBackupCollection('write_db');
$order_records_backup = $order_backup_write->pay_order_backup();
if(is_array($order_records_backup)){
    $fai = "pay_order备份新增失败" . json_encode($order_records_backup);
    $log_fail->warn($fai);
    exit("备份失败");
}

if($order_records_backup == 'no_data') {
    $str = "pay_order今日無資料备份";
    $log_success->warn($str);
    exit("备份成功");
}

if($order_records_backup == 'delete_fail') {
    $fai = "pay_order备份刪除失败";
    $log_fail->warn($fai);
    exit("备份失败");
}

if($order_records_backup == 'ok') {
    $str = "pay_order备份成功";
    $log_success->warn($str);
    exit("备份成功");
}
?>