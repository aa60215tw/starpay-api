<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class ProvinceCode extends PermissionDbModel
{

    public function getTable()
    {
        return "province_code";
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