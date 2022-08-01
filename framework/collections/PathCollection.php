<?php
require_once( FRAMEWORK_PATH . 'system/collections/PermissionDbCollection.php' );
require_once( FRAMEWORK_PATH . 'models/Path.php' );

class PathCollection extends PermissionDbCollection
{
    public function __construct($DbConfig  = 'read_db' ,DbHero $dao = null) {
        if(is_null($dao)) {
            $dao = new Db($DbConfig);
        }
        parent::__construct($dao);
    }

	public function getTable()
    {
		return "path";//表單名
	}

	public function getModelName()
    {
		return "Path";
	}

    public function path($search=array())
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
        if(array_key_exists("id", $search))
        {
            array_push($conditions, 'id = :id');
            $params[':id'] = $search['path_id'];
        }//where

        $this->dao->where($conditions,$params);
        $result["records"] = $this->dao->queryAll();
        return $result;
    }

    public function get_queryurl($search=array())
    {
        $result = array();
        $table = $this->getTable();
        $conditions = array('and','1=1');
        $params = array();

        $this->dao->fresh();
        $this->dao->select(array
        (
            'query'
        ));//select '*' from
        $this->dao->from("$table");//表單名

        if(array_key_exists("id", $search))
        {
            array_push($conditions, 'id = :id');
            $params[':id'] = $search['id'];
        }//where

        $this->dao->where($conditions,$params);
        $result["records"] = $this->dao->queryAll();
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
