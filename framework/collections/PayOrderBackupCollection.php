<?php
/**
 *	HomePageImageCollection code.
 *
 *	PHP version 5.3
 *
 *	@category Collection
 *	@package HomePageImage
 *	@author Rex chen <rexchen@synctech.ebiz.tw>
 *	@author Jai Chien <jaichien@synctech.ebiz.tw>
 *	@copyright 2015 synctech.com
 */

require_once( FRAMEWORK_PATH . 'system/collections/PermissionDbCollection.php' );
require_once( FRAMEWORK_PATH . 'models/PayOrder.php' );

/**
 *	HomePageImageCollection Access HomePageImage entity collection.
 *
 *	PHP version 5.3
 *
 *	@category Collection
 *	@package HomePageImage
 *	@author Rex chen <rexchen@synctech.ebiz.tw>
 *	@author Jai Chien <jaichien@synctech.ebiz.tw>
 *	@copyright 2015 synctech.com
 */
class PayOrderBackupCollection extends PermissionDbCollection {
    public function __construct($DbConfig  = 'read_db' ,DbHero $dao = null) {
        if(is_null($dao)) {
            $dao = new Db($DbConfig);
        }
        parent::__construct($dao);
    }

    /**
     *	Get the entity table name.
     *
     *	@return string
     */
    public function getTable() {
        return "pay_order_backup";
    }

    public function getModelName() {
        return "PayOrderBackup";
    }
    public function pay_order_backup()
    {
        $where = "DATE_FORMAT(order_time,'%Y-%m-%d')=DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-%d')";
        try {
            $this->dao->transaction();
            $backup = $this->dao->insert_select("pay_order_backup", "pay_order", "", "", $where);
            $delete = $this->dao->delete("pay_order", $where);
            $this->dao->commit();
            if(is_array($backup))
                return $backup;
            if($backup == 0)
                return 'no_data';
            if($delete != $backup)
                return 'delete_fail';
            return 'ok';
        } catch(Exception  $e) {
            $log_fail = new LoggerHelper('rollback', PAY_ORDER_BACKUP_FAIL);
            $fai = "pay_order备份失败(系统错误)";
            $log_fail->warn($fai);
            $this->dao->rollback();
        }
    }

    public function validAttributes($attributes) {


        if(array_key_exists("id", $attributes) ){
            throw new Exception("Error cannot has param [id]", 1);
        }

        return true;

    }

    /**
     *	Get Primary key attribute name
     *
     *	@return string
     */
    public function getPrimaryAttribute() {
        return "id";
    }

}



?>
