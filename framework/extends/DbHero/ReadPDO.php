<?php

/**
 * Class CrmMasterPDO
 */
class ReadPDO {

    static private $pdo = null;
    static function getInstance()
    {

        try {
            if (is_null(self::$pdo)) {
                self::$pdo = new PDO(
                    'mysql:host=' . READ_DB_HOST . ';dbname=' . READ_DB_NAME,
                    READ_DB_LOGIN_USER,
                    READ_DB_LOGIN_PASSWORD,
                    array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
                );
            }
            return self::$pdo;
        }
        catch (PDOException $e) {
            self::changeHost();
            throw new Exception($e->getMessage(), 1);
        }
    }

    static function changeHost()
    {
        $time = time()-DB_ENV_TIME;
        if($time > 600){
            switch (READ_DB_HOST){
                case "104.199.197.154":
                    $host = "130.211.248.83";
                    break;
                case "130.211.248.83":
                    $host = "104.199.197.154";
                    break;
                default:
                    $host = "104.199.197.154";
                    break;
            }
            $filename = ROOT. '.env';
            $data = "READ_DB_HOST=". $host."\n";
            $data2 = "DB_ENV_TIME=". time()."\n";
            $shared = new Shared();
            $shared->changeFile($filename,"READ_DB_HOST",$data);
            $shared->changeFile($filename,"DB_ENV_TIME",$data2);
        }
    }
}

?>