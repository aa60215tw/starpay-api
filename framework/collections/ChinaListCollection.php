<?php

require_once( FRAMEWORK_PATH . 'system/collections/PermissionDbCollection.php' );
require_once( FRAMEWORK_PATH . 'models/ChinaList.php' );

class ChinaListCollection extends PermissionDbCollection
{
    public function __construct($DbConfig  = 'read_db' ,DbHero $dao = null) {
        if(is_null($dao)) {
            $dao = new Db($DbConfig);
        }
        parent::__construct($dao);
    }

    public function getChina($search=array())
    {
        $result = array();
        $table = $this->getTable();
        $conditions = array('and','1=1');
        $params = array();

        $this->dao->fresh();
        $this->dao->select(array('*'));
        $this->dao->from("$table");//表單名

        if(array_key_exists("districtAdcode", $search))
        {
            $districtAdcode = substr($search['districtAdcode'] , 0 ,2);
            array_push($conditions, 'AreaCode like :districtAdcode');
            $params[':districtAdcode'] = "$districtAdcode%";
        }

        $this->dao->where($conditions,$params);
        $result = $this->dao->queryAll();
        return $result;
    }

	public function getTable()
    {
		return "china_list";//表單名
	}

	public function getModelName()
    {
		return "ChinaList";
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
