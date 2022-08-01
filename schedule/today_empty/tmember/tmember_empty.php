<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/configs/sys.config.inc.php');
require_once(FRAMEWORK_PATH . '/controllers/MultipayController.php');
require_once(FRAMEWORK_PATH . 'collections/PoolCollection.php');
require_once(FRAMEWORK_PATH . 'extends/Shared.php');

$log_success= new LoggerHelper('pool_empty_success', TODAY_EMPTY_LOG);
$shared = new Shared();
$pool_write = new PoolCollection('write_db');
$pool_empty = $pool_write->pool_empty();

if($pool_empty == 'ok') {
    $str = "todayUse归零成功";
    $shared->slack($str , SLACK_POOL_URL);
    $log_success->warn($str);
    exit("归零成功");
}
?>