<?php
include_once __DIR__ . "/autoload.php";

class CashFlowFactory
{
    public function create( $type ) {
        $className = $type . 'Provider';
        return new $className();
    }
}