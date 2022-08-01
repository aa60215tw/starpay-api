<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class PathAccount extends PermissionDbModel 
{
    
	public function getTable() 
    {
		return "path_account";
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