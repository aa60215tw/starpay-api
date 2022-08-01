<?php

/**
 * Class AuthCenterPDO
 */
class WritePDO {

    static private $pdo = null;
    static function getInstance()
    {
        try {
            if (is_null(self::$pdo)) {
                self::$pdo = new PDO(
                    'mysql:host=' . WRITE_DB_HOST . ';dbname=' . WRITE_DB_NAME ,
                    WRITE_DB_LOGIN_USER,
                    WRITE_DB_LOGIN_PASSWORD,
                    array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
                );
            }
            return self::$pdo;
        }
        catch (PDOException $e) {
            throw new Exception($e->getMessage(), 1);
        }
    }
}

?>