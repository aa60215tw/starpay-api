<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class PoolRule extends PermissionDbModel
{

    public function getTable()
    {
        return "pool_rule";
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