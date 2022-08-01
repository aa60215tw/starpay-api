<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class System extends PermissionDbModel
{

    public function getTable()
    {
        return "system";
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