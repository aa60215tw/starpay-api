<?php

require_once( FRAMEWORK_PATH . 'system/models/PermissionDbModel.php' );

class Member extends PermissionDbModel 
{
    
	public function getTable() 
    {
		return "member";
	}

	public function validAttributes($attributes) 
    {
		return true;
	}

	public function getPrimaryAttribute() 
    {
		return "user_id";
	}
   
}

?>