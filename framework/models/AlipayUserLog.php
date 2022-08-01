<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class AlipayUserLog extends PermissionDbModel
{

    public function getTable()
    {
        return "alipay_user_log";
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