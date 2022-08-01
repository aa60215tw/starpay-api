<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 2018/8/10
 * Time: 下午 04:58
 */

class Des
{
    public function sign3des($key,$list){
        $data = openssl_encrypt($list,'des-ede3',$key,0);
        return base64_decode($data);
    }

    public function desing3des($key,$decrypted){
        $result= openssl_decrypt($decrypted,'des-ede3',$key,0);
        return $result;
    }
}