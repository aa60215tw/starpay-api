<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class Bank extends PermissionDbModel
{

    public function getTable()
    {
        return "bank";
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