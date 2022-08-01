<?php
require_once(__DIR__ . '/DbHero.php');
require_once(__DIR__ . '/ReadPDO.php');
require_once(__DIR__ . '/WritePDO.php');


/**
 * Class Db
 * @author Rex Chen <chen.cyr@gmail.com>
 */
class Db extends DbHero {
	protected function _config() {
		return array(
            WRITE_DB_PDO_NAME => WritePDO::getInstance(),
            READ_DB_PDO_NAME  => ReadPDO::getInstance(),
		);
	}
}

?>
