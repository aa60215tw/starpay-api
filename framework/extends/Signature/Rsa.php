<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 2018/4/12
 * Time: 下午 03:49
 */

class Rsa
{
    /*
     * *RSA私鑰加密
     */
    public function rsa_sign($data,$privateKey,$OPENSSL = OPENSSL_ALGO_SHA1,$charset='base64') {
        $key = openssl_get_privatekey($privateKey);
        openssl_sign($data, $sign, $key,$OPENSSL);
        openssl_free_key($key);
        if($charset == 'base64')
            $sign = base64_encode($sign);
        else if($charset == 'bin2hex')
            $sign = bin2hex($sign);
        return $sign;
    }

    /*
     * *RSA私鑰解密
     */
    function rsa_decrypt($data,$pool)
    {
        $privatekey = $pool['rsakey'];
        $key = openssl_get_privatekey($privatekey);
        $a_key = openssl_pkey_get_details($key);
        $part_len = ceil($a_key['bits'] / 8);
        $base64_decoded = base64_decode($data);
        $parts = str_split($base64_decoded, $part_len);
        $decrypted = "";
        foreach ($parts as $part) {
            $decrypted_temp = '';
            openssl_private_decrypt($part, $decrypted_temp,$key);
            $decrypted .= $decrypted_temp;
        }
        return $decrypted;
    }

    /*
     * *RSA公鑰加密
     */
    public function rsa_pubsign($data,$pool) {
        $publicKey = $pool['key1'];
        $pubKey = openssl_get_publickey($publicKey);
        $a_key = openssl_pkey_get_details($pubKey);
        $part_len = ceil($a_key['bits'] / 8) - 11;
        $parts = str_split($data, $part_len);
        $encrypted = '';
        foreach ($parts as $part) {
            $encrypted_temp = '';
            openssl_public_encrypt($part, $encrypted_temp, $pubKey);
            $encrypted .= $encrypted_temp;
        }

        return base64_encode($encrypted);
    }

    /*
     * *RSA公鑰驗簽
     */
    public function rsa_verify($data,$pool,$sign_str,$OPENSSL = OPENSSL_ALGO_SHA1,$charset='base64') {
        $publicKey = $pool['key1'];
        if($charset == 'base64'){
            $sign = base64_decode ($sign_str);
        } else if($charset == 'bin2hex'){
            $sign = hex2bin($sign_str);
        }
        $result = openssl_verify ( $data, $sign, $publicKey,$OPENSSL);
        return $result;
    }

    /*
     * *RSA公鑰解密
     */
    public function rsa_pub_decrypt($data,$publicKey) {
        $pubKey = openssl_get_publickey($publicKey);
        $data = base64_decode($data);
        $len = strlen($data);
        $i=0;
        $result = '';
        while($len-$i>0){
            $encrypted = '';
            if($len-$i>128){
                openssl_public_decrypt(substr($data, $i,128),$encrypted,$pubKey,OPENSSL_PKCS1_PADDING);
            } else {
                openssl_public_decrypt(substr($data, $i,$len-$i),$encrypted,$pubKey,OPENSSL_PKCS1_PADDING);
            }
            $result .= $encrypted;
            $i += 128;
        }
        return $result;
    }
}