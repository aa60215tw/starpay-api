<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class ChinaList extends PermissionDbModel
{

    public function getTable()
    {
        return "china_list";
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