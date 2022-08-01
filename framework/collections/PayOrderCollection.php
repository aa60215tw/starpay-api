<?php
/**
*	HomePageImageCollection code.
*
*	PHP version 5.3
*
*	@category Collection
*	@package HomePageImage
*	@author Rex chen <rexchen@synctech.ebiz.tw>
*	@author Jai Chien <jaichien@synctech.ebiz.tw>
*	@copyright 2015 synctech.com
*/

require_once( FRAMEWORK_PATH . 'system/collections/PermissionDbCollection.php' );
require_once( FRAMEWORK_PATH . 'models/PayOrder.php' );

/**
*	HomePageImageCollection Access HomePageImage entity collection.
*
*	PHP version 5.3
*
*	@category Collection
*	@package HomePageImage
*	@author Rex chen <rexchen@synctech.ebiz.tw>
*	@author Jai Chien <jaichien@synctech.ebiz.tw>
*	@copyright 2015 synctech.com
*/
class PayOrderCollection extends PermissionDbCollection {
    public function __construct($DbConfig  = 'read_db' ,DbHero $dao = null) {
        if(is_null($dao)) {
            $dao = new Db($DbConfig);
        }
        parent::__construct($dao);
    }

	/**
	*	Get the entity table name.
	*
	*	@return string 
	*/
	public function getTable() {
		return "pay_order";
	}

	public function getModelName() {
		return "PayOrder";
	}
    public function path_order($search=array())
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
        if(array_key_exists("pay_order_number", $search))
        {
            array_push($conditions, 'pay_order_number = :pay_order_number');
            $params[':pay_order_number'] = $search['pay_order_number'];
        }//where

        if(array_key_exists("ordernumber", $search))
        {
            array_push($conditions, 'pay_order_number = :pay_order_number');
            $params[':pay_order_number'] = $search['ordernumber'];
        }//where

        if(array_key_exists("upstream_order_number", $search))
        {
            array_push($conditions, 'upstream_order_number = :upstream_order_number');
            $params[':upstream_order_number'] = $search['upstream_order_number'];
        }//where

        if(array_key_exists("user_id", $search))
        {
            array_push($conditions, 'user_id = :user_id');
            $params[':user_id'] = $search['user_id'];
        }//where

        $this->dao->where($conditions,$params);
        $result["records"] = $this->dao->queryAll();
        return $result;
    }

    public function path_order_verify($search=array())
    {
        $result = array();
        $table = $this->getTable();
        $conditions = array('and','1=1');
        $params = array();

        $this->dao->fresh();
        $this->dao->select(array
        (
            "COUNT('id') COUNT"
        ));//select '*' from
        $this->dao->from("$table");//表單名

        if(array_key_exists("pay_order_number", $search))
        {
            array_push($conditions, 'pay_order_number = :pay_order_number');
            $params[':pay_order_number'] = $search['pay_order_number'];
        }//where

        $this->dao->where($conditions,$params);

        $result = $this->dao->query();
        return $result;
    }

    public function path_order_saearch_key($search=array())
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

        if(array_key_exists("upstream_order_number", $search))
        {
            array_push($conditions, 'upstream_order_number = :upstream_order_number');
            $params[':upstream_order_number'] = $search['upstream_order_number'];
        }//where

        if(array_key_exists("payment_status", $search))
        {
            array_push($conditions, 'payment_status = :payment_status');
            $params[':payment_status'] = $search['payment_status'];
        }//where

        $this->dao->where($conditions,$params);
        $result["records"] = $this->dao->queryAll();
        return $result;
    }

	public function validAttributes($attributes) {


		if(array_key_exists("id", $attributes) ){
        	throw new Exception("Error cannot has param [id]", 1);
        }

        return true;

	}

	/**
	*	Get Primary key attribute name
	*
	*	@return string
	*/
	public function getPrimaryAttribute() {
		return "id";
	}

}



?>
