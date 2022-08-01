<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class ErrorMsg extends PermissionDbModel
{

    public function getTable()
    {
        return "error_msg";
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