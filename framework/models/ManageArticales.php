<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class ManageArticales extends PermissionDbModel
{

    public function getTable()
    {
        return "manage_articales";
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