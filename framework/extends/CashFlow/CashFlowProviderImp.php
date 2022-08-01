<?php


interface CashFlowProviderImp {

    /**
     * @param $data
     * @param $collection
     * @return mixed
     */
    public function send($data);

    /**
     * @param $swift
     * @return mixed
     */
    public function getBankcode($swift);

    /**
     * @param array $orderList
     * @return mixed
     */
    public function getOrder($orderList = array());
}

?>