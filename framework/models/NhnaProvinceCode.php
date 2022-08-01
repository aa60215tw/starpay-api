<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class NhnaProvinceCode extends PermissionDbModel
{

    public function getTable()
    {
        return "nhna_province_code";
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
