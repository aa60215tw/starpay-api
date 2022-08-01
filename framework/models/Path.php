<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class Path extends PermissionDbModel
{

    public function getTable()
    {
        return "path";
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