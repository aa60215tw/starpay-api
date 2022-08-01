<?php
require_once( FRAMEWORK_PATH . 'system/collections/PermissionDbCollection.php' );
require_once( FRAMEWORK_PATH . 'models/PathAccount.php' );

class PathAccountCollection extends PermissionDbCollection 
{

    public function __construct($DbConfig  = 'read_db' ,DbHero $dao = null) {
        if(is_null($dao)) {
            $dao = new Db($DbConfig);
        }
        parent::__construct($dao);
    }

	public function getTable() 
    {
		return "path_account";//表單名
	}

	public function getModelName() 
    {
		return "PathAccount";
	}
    
    public function path_account($search=array()) 
    {
        $result = array();
        $table = $this->getTable();
        $conditions = array('and','1=1');
        $params = array();

        $this->dao->fresh();
        $this->dao->select(array
        (
            '*'
        ));//select '*' from
        $this->dao->from("$table");//表單名
        /*array_push($conditions, 'account = :test1');
        $params[':test1'] = 'test1';*/
        if(array_key_exists("user_id", $search))
        {
            array_push($conditions, 'user_id = :user_id');
            $params[':user_id'] = $search['user_id'];
        }//where

        if(array_key_exists("swift", $search))
        {
            array_push($conditions, 'swift = :swift');
            $params[':swift'] = $search['swift'];
        }//where

        array_push($conditions, 'status = :status');
        $params[':status'] = "1";

        $this->dao->where($conditions,$params);
        $result["records"] = $this->dao->queryAll();
        return $result;
    }

    public function seach_path_account($search=array())
    {
        $table = $this->getTable();
        $conditions = array('and','1=1');
        $params = array();

        $this->dao->fresh();
        $this->dao->select(array
        (
            'p.swift_path'
        ));//select '*' from
        $this->dao->from("$table pa");//表單名

        $this->dao->leftJoin(
            'path p',
            'p.id=pa.path_id');

        if(array_key_exists("user_id", $search))
        {
            array_push($conditions, 'pa.user_id = :user_id');
            $params[':user_id'] = $search['user_id'];
        }

        if(array_key_exists("swift", $search))
        {
            array_push($conditions, 'pa.swift = :swift');
            $params[':swift'] = $search['swift'];
        }

        array_push($conditions, 'pa.status = :status');
        $params[':status'] = "1";

        $this->dao->where($conditions,$params);
        $result = $this->dao->query();
        return $result;
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
		return "id";
	}

}

?>
