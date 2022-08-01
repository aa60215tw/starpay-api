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
require_once( FRAMEWORK_PATH . 'models/Pool.php' );

class PoolCollection extends PermissionDbCollection {
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
		return "pool";
	}

	public function getModelName() {
		return "Pool";
	}

    public function searchRecords($search = array()) {
        $conditions = array('and','1=1');
        if(array_key_exists('order_time', $search)) {
            array_push($conditions, "(CURTIME() between deal_time_start and deal_time_end OR 
            (NOT CURTIME() BETWEEN `deal_time_end` AND `deal_time_start` AND `deal_time_start` > `deal_time_end`))");
        }

        if(array_key_exists('user_id', $search)) {
            $user_id = $search['user_id'];
            array_push($conditions, "user_id = '$user_id'");
        }

        if(array_key_exists('pay_amount', $search)) {
            $pay_amount = $search['pay_amount'];
            array_push($conditions, "(once_deal_money_low <= '$pay_amount' and once_deal_money_high >= '$pay_amount')");
        }

//        if(array_key_exists('t_mid', $search)) {
//            $t_mid = $search['t_mid'];
//            array_push($conditions, "pool.t_mid = '$t_mid'");
//        }

        if(array_key_exists('path_id', $search)) {
            $path_id = $search['path_id'];
            array_push($conditions, "path_id = '$path_id'");
        }

        if(array_key_exists('swift', $search)) {
            $swift = $search['swift'];
            array_push($conditions, "swift = '$swift'");
        }

        if(array_key_exists('area1', $search)) {
            $area1 = $search['area1'];
            array_push($conditions, "area1 = '$area1'");
        }

        if(array_key_exists('area2', $search)) {
            $area2 = $search['area2'];
            array_push($conditions, "area2 = '$area2'");
        }

        if(array_key_exists('area3', $search)) {
            $area3 = $search['area3'];
            array_push($conditions, "area3 = '$area3'");
        }

        if($search['today_use'] == true){
            array_push($conditions, "today_use <= deal_use_high");
        }

        if($search['today_money'] == true) {
            array_push($conditions, "today_money <= deal_money_high");
        }

        if($search['today_use'] == false){
            array_push($conditions, "today_use <= deal_use_limit");
        }

        if($search['today_money'] == false){
            array_push($conditions, "today_money <= deal_money_limit");
        }

        if(array_key_exists('limited_time', $search)){
            array_push($conditions, "(DATE(last_use_time) != CURDATE() OR last_use_time >= DATE_SUB(NOW(), INTERVAL 2 HOUR))");
        }

        // 频率
        if (isset($search['wait_time']) && ((int)$search['wait_time'] > 0) ) {
            $date = date("Y-m-d H:i:s", strtotime('-'.(int)$search['wait_time'].' seconds', time()));
            array_push($conditions, " last_use_time <= '" . $date . "'");
            // array_push($conditions, " TIMESTAMPDIFF(SECOND, last_use_time, NOW()) <= " . (int)$search['wait_time']);
        }

        // 排序
        $order_by = 'last_use_time ASC ';
        if (!empty($search['order_by'])) {
            switch ($search['order_by']) {
                case 'success_rate':
                    $order_by = ' (success_number / GREATEST(1, use_number)) DESC, last_use_time ASC ';
                    break;
                case 'newest':
                    $order_by = ' last_use_time DESC ';
                    break;
                case 'id_desc':
                    $order_by = ' id DESC ';
                    break;
            }
        }
        // 符合店家营业时间优先
        $order_by = ' deal_time_end ASC, ' . $order_by;

        array_push($conditions, 'status = 1');

        $op = ' ' . array_shift( $conditions ) . ' ';
        $where = '';
        $where1 = array();
        foreach( $conditions as $name => $value )
            $where .= $value . $op;

        $where1[0] = substr( $where, 0, (strlen($op) * -1) );

        $sql = "SELECT *,p.id AS pool_id FROM pool AS p JOIN
        (SELECT id FROM pool WHERE $where1[0] ORDER BY $order_by LIMIT 1 )p1 ON p.id=p1.id";

        $result = $this->dao->runQuery($sql);
        $result = empty($result)?$result:$result[0];
        return $result;
    }

    public function pool_saearch_key($search=array() ) {
        $table = $this->getTable();
        $conditions = array('and','1=1');
        $params = array();

        $this->dao->fresh();
        $this->dao->select(array(
            't_account','t_pass','`key`','rsakey','key1','t_card','id as pool_id'
        ));

        $this->dao->from("$table ");

        if(array_key_exists('user_id', $search)) {
            array_push($conditions, 'user_id = :user_id');
            $params[':user_id'] = $search['user_id'];
        }

        if(array_key_exists('t_mid', $search)) {
            array_push($conditions, 't_mid = :t_mid');
            $params[':t_mid'] = $search['t_mid'];
        }

        if(array_key_exists('path_id', $search)) {
            array_push($conditions, 'path_id = :path_id');
            $params[':path_id'] = $search['path_id'];
        }

        if(array_key_exists('swift', $search)) {
            array_push($conditions, 'swift = :swift');
            $params[':swift'] = $search['swift'];
        }

        $this->dao->where($conditions,$params);

        $result = $this->dao->query();
        return $result;
    }

    public function pool_empty()
    {
        try {
            $this->dao->transaction();
            $this->dao->update("pool", array('today_use' => 0,'today_money' => 0), '1 = 1');
            $this->dao->update("pool", array('deal_use_high' => 8, 'deal_use_limit' => 8), "path_id = 28 AND user_id = 'bab00027'");
            $this->dao->commit();
            return 'ok';
        } catch(Exception  $e) {
            $log_fail = new LoggerHelper('pool_empty_fail', TODAY_EMPTY_LOG);
            $fai = "交易归零失败";
            $log_fail->warn($fai);
            $this->dao->rollback();
        }
    }

	/**
	*	Check attributes is valid.
	*
	*	@param $attributes 	array Attributes want to checked.
	*	@return bool 		If valid return true.
	*/
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
