<?php

require_once( FRAMEWORK_PATH . 'system/collections/PermissionDbCollection.php' );
require_once( FRAMEWORK_PATH . 'models/ProvinceCode.php' );

class ProvinceCodeCollection extends PermissionDbCollection
{
    public function __construct($DbConfig  = 'read_db' ,DbHero $dao = null) {
        if(is_null($dao)) {
            $dao = new Db($DbConfig);
        }
        parent::__construct($dao);
    }

	public function getTable()
    {
		return "province_code";//表單名
	}

	public function getModelName()
    {
		return "models";
	}

    public function province($search=array())
    {

        $result = array();
        $table = $this->getTable();
        $conditions = array('and','1=1');
        $params = array();

        $this->dao->select(array
        (
            '*'
        ));

        $this->dao->from("$table");

        if(array_key_exists("id", $search))
        {
            array_push($conditions, 'id = :id');
            $params[':id'] = $search['id'];
        }

        if(array_key_exists("area1", $search))
        {
            $area1 = $search['area1'];
            array_push($conditions, '`area` LIKE :area1');
            $params[':area1'] = "%$area1%";
        }

        if(array_key_exists("area2", $search))
        {
            $area2 = $search['area2'];
            array_push($conditions, '`area` LIKE :area2');
            $params[':area2'] = "%$area2%";
        }

        $this->dao->order('RAND()');

        $this->dao->where($conditions,$params);
        $result["records"] = $this->dao->query();
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
