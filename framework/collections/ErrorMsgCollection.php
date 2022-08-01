<?php

require_once( FRAMEWORK_PATH . 'system/collections/PermissionDbCollection.php' );
require_once( FRAMEWORK_PATH . 'models/Member.php' );

class ErrorMsgCollection extends PermissionDbCollection
{
    public function __construct($DbConfig  = 'read_db' ,DbHero $dao = null) {
        if(is_null($dao)) {
            $dao = new Db($DbConfig);
        }
        parent::__construct($dao);
    }

    public function getTable()
    {
        return "error_msg";//表單名
    }

    public function getModelName()
    {
        return "ErrorMsg";
    }

    public function validAttributes($attributes)
    {
        if(array_key_exists("id", $attributes) )
        {
            throw new Exception("Error cannot has param [id]", 1);
        }
        return true;
    }

    public function getPrimaryAttribute()
    {
        return "user_id";
    }

}

?>
