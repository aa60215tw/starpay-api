<?php
require_once(FRAMEWORK_PATH . 'system/collections/PermissionDbCollection.php');
require_once(FRAMEWORK_PATH . 'models/PoolRule.php');

class PoolRuleCollection extends PermissionDbCollection
{
    public function __construct($DbConfig = 'read_db', DbHero $dao = null)
    {
        if (is_null($dao)) {
            $dao = new Db($DbConfig);
        }
        parent::__construct($dao);
    }

    public function getTable()
    {
        return "pool_rule";//表單名
    }

    public function getModelName()
    {
        return "PoolRule";
    }

    public function getPoolrecord($data)
    {
        $table = $this->getTable();
        $conditions = array('and', '1=1');
        $params = array();

        $this->dao->fresh();
        $this->dao->select(array
        (
            '*'
        ));//select '*' from
        $this->dao->from("$table");//表單名

        if (array_key_exists("user_id", $data)) {
            array_push($conditions, "user_id = :user_id");
            $params[':user_id'] = $data['user_id'];
        }

        if (array_key_exists("member_ip", $data)) {
            array_push($conditions, "pool_user_ip = :pool_user_ip");
            $params[':pool_user_ip'] = $data['member_ip'];
        }

        if (array_key_exists('path_id', $data)) {
            array_push($conditions, "path_id = :path_id");
            $params[':path_id'] = $data['path_id'];
        }

        if (array_key_exists('order_time', $data)) {
            $order_time = date("H:i:s", strtotime($data['order_time']));
            array_push($conditions, "('$order_time' between deal_time_start and deal_time_end OR 
            (NOT '$order_time' BETWEEN `deal_time_end` AND `deal_time_start` AND `deal_time_start` > `deal_time_end`))");
        }

        if (array_key_exists('pay_amount', $data)) {
            $pay_amount = $data['pay_amount'];
            array_push($conditions, "(once_deal_money_low <= '$pay_amount' and once_deal_money_high >= '$pay_amount')");
        }

        array_push($conditions, "reset_count < :reset_count");
        $params[':reset_count'] = LIMIT_NUMBER;

        $this->dao->where($conditions, $params);
        $this->dao->order('reset_count ASC');
        $result = $this->dao->queryAll();
        return $result;
    }


    public function validAttributes($attributes)
    {
        if (array_key_exists("id", $attributes)) {
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
