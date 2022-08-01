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
require_once( FRAMEWORK_PATH . 'models/MessageBoard.php' );

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
class MessageBoardCollection extends PermissionDbCollection {

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
		return "message_board";
	}

	public function getModelName() {
		return "MessageBoard";
	}

    public function searchRecords($pageNo, $pageSize, $search=array() ) {

        $result = $this->getDefaultRecords($pageNo, $pageSize);
        $table = $this->getTable();
        $conditions = array('and','1=1');
        $params = array();

        $this->dao->fresh();
        $this->dao->select(array(
            'mb.*' , 'cu.account' , 'cu.name' , 'cu.avatar_img' , 'sl.title'
        ));

        $this->dao->leftJoin(
            'consumer_user cu',
            'cu.id=mb.consumer_user_id');

        $this->dao->leftJoin(
            'sungirl_list sl',
            'sl.id=mb.sungirl_list_id');


        $this->dao->from("$table mb");
        $this->dao->group('mb.id');
        $this->dao->order('mb.create_time DESC');
        if(array_key_exists('keyword', $search)) {
            $keyword = $search['keyword'];
            array_push($conditions, 'sl.title like :keyword or cu.name like :keyword');
            $params[':keyword'] = "%$keyword%";
        }

        if(array_key_exists('sungirl_list_id', $search)) {
            array_push($conditions, 'mb.sungirl_list_id = :sungirl_list_id');
            $params[':sungirl_list_id'] = $search['sungirl_list_id'];
        }

        $this->dao->where($conditions,$params);
        $result['recordCount'] = intval($this->dao->queryCount());
        $result['totalRecord'] = $result['recordCount'];
        $result["totalPage"] = intval(ceil($result['totalRecord'] / $pageSize));
        $this->dao->paging($pageNo, $pageSize);

        $result["records"] = $this->dao->queryAll();

        return $result;
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
