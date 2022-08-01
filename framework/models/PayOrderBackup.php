<?php
require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class PayOrderBackup extends PermissionDbModel
{

    public function getTable()
    {
        return "pay_order_backup";
    }

    public function validAttributes($attributes)
    {
        return true;
    }

    public function getPrimaryAttribute()
    {
        return "id";
    }

}

?>