<?PHP
define('ROOT', str_replace('\\','/',dirname(dirname(__FILE__)).'/'));
require_once(dirname(dirname(__FILE__)) .  '/vendor/autoload.php' );
$dotenv = new Dotenv\Dotenv(ROOT);
$dotenv->load();
/********************************************
 *
 * 路徑定義
 *
 ********************************************/
define('FRAMEWORK_PATH', ROOT . getenv('FRAMEWORK_PATH'));
define('UPLOAD', ROOT . getenv('UPLOAD'));
define('LOG_PATH', ROOT . getenv('LOG_PATH')); //系統如果發生問題時，會把記錄存在這個log檔
define('URL', getenv('URL'));
define('CASH_URL', URL . getenv('CASH_URL'));
define('CALL_URL', URL . getenv('CALL_URL'));
define('OBTP_URL', URL . getenv('OBTP_URL'));
define('TENPAY_URL', URL . getenv('TENPAY_URL'));
define('REEOR_URL', URL . getenv('REEOR_URL'));
define('QRCODE_URL', URL . getenv('QRCODE_URL'));
define('ZONG_URL', URL . getenv('ZONG_URL'));
define('ZONG_JUMP_FULL_URL', getenv('ZONG_JUMP_FULL_URL'));
define('ZONG_API_URL', URL . getenv('ZONG_API_URL'));
define('WALLET_URL', getenv('WALLET_URL'));
define('MY_IP', getenv('MY_IP'));
define('BACK_URL', getenv('BACK_URL'));
define('BACK_URL1', getenv('BACK_URL1'));
define('API_URL', getenv('API_URL'));
define('SLACK_URL', getenv('SLACK_URL'));
define('SLACK_POOL_URL', getenv('SLACK_POOL_URL'));
define('AUTH_KEY', getenv('AUTH_KEY'));
define('TRANSFER_NUM', getenv('TRANSFER_NUM'));
define('LIMIT_NUMBER', getenv('LIMIT_NUMBER'));
define('PROXY_SERVER', getenv('PROXY_SERVER'));

/********************************************
 *
 * 防止env被重复更改的时间参数
 *
 ********************************************/
define('DB_ENV_TIME', getenv('DB_ENV_TIME'));
/********************************************
 *
 * 请求 log檔
 *
 ********************************************/
define('PATH_REQUEST_LOG', ROOT . getenv('PATH_REQUEST_LOG'));
define('CLIENT_REQUEST_LOG', ROOT . getenv('CLIENT_REQUEST_LOG'));
/********************************************
 *
 * 支付callback log檔
 *
 ********************************************/
define('PAY_LOG_CALL', ROOT . getenv('PAY_LOG_CALL'));
define('PAY_LOG_FAIL', ROOT . getenv('PAY_LOG_FAIL'));
define('PAY_LOG_SUCCESS', ROOT . getenv('PAY_LOG_SUCCESS'));
define('CALLBACK_SUCCESS', ROOT . getenv('CALLBACK_SUCCESS'));
/********************************************
 *
 * 代收訂單資料庫備份 log檔
 *
 ********************************************/
define('PAY_ORDER_BACKUP', ROOT . getenv('PAY_ORDER_BACKUP'));
define('ORDER_LOG', ROOT . getenv('ORDER_LOG'));
/********************************************
 *
 * 上游回傳錯誤 log檔
 *
 ********************************************/
define('UPSTREAM_LOG', ROOT . getenv('UPSTREAM_LOG'));
/********************************************
 *
 * IP log檔
 *
 ********************************************/
define('IP_LOG', ROOT . getenv('IP_LOG'));
define('IP_OFFLINE_DB', ROOT . getenv('IP_OFFLINE_DB'));
/********************************************
 *
 * 清除每日交易 log檔
 *
 ********************************************/
define('TODAY_EMPTY_LOG', ROOT . getenv('TODAY_EMPTY_LOG'));
/********************************************
 *
 * 寫入DB
 *
 ********************************************/
define('WRITE_DB_HOST', getenv('WRITE_DB_HOST'));
define('WRITE_DB_NAME', getenv('WRITE_DB_NAME'));
define('WRITE_DB_LOGIN_USER', getenv('WRITE_DB_LOGIN_USER'));
define('WRITE_DB_LOGIN_PASSWORD', getenv('WRITE_DB_LOGIN_PASSWORD'));
define('WRITE_DB_PDO_NAME', getenv('WRITE_DB_PDO_NAME'));
/********************************************
 *
 * 讀取DB
 *
 ********************************************/
define('READ_DB_HOST', getenv('READ_DB_HOST'));
define('READ_DB_NAME', getenv('READ_DB_NAME'));
define('READ_DB_LOGIN_USER', getenv('READ_DB_LOGIN_USER'));
define('READ_DB_LOGIN_PASSWORD', getenv('READ_DB_LOGIN_PASSWORD'));
define('READ_DB_PDO_NAME', getenv('READ_DB_PDO_NAME'));


/********************************************
 *
 * 錯誤訊息
 *
 ********************************************/
define('SERVER_ERROR_MSG',getenv('SERVER_ERROR_MSG'));
define('PERMISSION_ERROR_MSG',getenv('PERMISSION_ERROR_MSG'));
define('NOCONTENT_MSG',getenv('NOCONTENT_MSG'));
/********************************************
 *
 * 時區定義
 *
 ********************************************/
date_default_timezone_set('Asia/Taipei');
/********************************************
 *
 * 除錯訊息
 *
 ********************************************/
ini_set("display_errors", getenv('display_errors'));
ini_set("max_execution_time", getenv('max_execution_time'));
error_reporting(getenv('error_reporting'));
/********************************************
 *
 * 环境宣告
 *
 ********************************************/
define('APP_ENV', getenv('APP_ENV'));

/********************************************
 *
 * 基本程式
 *
 ********************************************/


require_once FRAMEWORK_PATH . 'synature.php';
?>