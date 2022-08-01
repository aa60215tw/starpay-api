<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class PathBank extends PermissionDbModel 
{
    
	public function getTable() 
    {
		return "path_bank";
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