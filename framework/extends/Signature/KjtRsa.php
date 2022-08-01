<?php
/**
 * 签名算法
 */
Class KjtRsa
{
    const SIGN = 'sign';
    const SIGN_TYPE = 'sign_type';
    private static $privateKeyId;
    private static $publicKeyId;

    public function __construct($privateKey, $publicKey)
    {
        self::$privateKeyId = $privateKey;
        self::$publicKeyId = $publicKey;

        if(!extension_loaded("openssl")){
            ' is not installed';
        }
    }

    /**
     * 签名
     *
     * @param $oriArr  需要签名的数组
     * @return 签名结果(BASE64编码)
     */
    public function sign(Array $oriArr)
    {
        $text = $this->createLinkString($oriArr);
        openssl_sign($text, $signature, self::$privateKeyId);
        return base64_encode($signature);
    }


    /**
     * 验签
     *
     * @param oriArr 未签名的数组
     * @param sign    签名结果
     * @return 验签结果 bool
     */

    public function verify(Array $oriArr, $sign)
    {
        $oriText = $this->createLinkString($oriArr);
        $ok = openssl_verify($oriText, base64_decode($sign), self::$publicKeyId);
        if ($ok == 1) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * <p>
     * 私钥加密
     * </p>
     * encryptByPrivateKey
     * @param oriText 源数据
     * @return
     */
    public function encrypt($oriText)
    {
        $encrypted = '';
        $res = openssl_pkey_get_private(self::$privateKeyId);
        if(!$res){
            return $encrypted;
        }
        $detailArr = openssl_pkey_get_details($res);
        $encryptBlockSize = $detailArr['bits']/8-11;
        $plainData = str_split($oriText, $encryptBlockSize);
        foreach ($plainData as $chunk) {
            $partialEncrypted = '';
            //using for example _PKCS1_PADDING as padding
            $encryptionOk = openssl_private_encrypt($chunk, $partialEncrypted, self::$privateKeyId, OPENSSL_PKCS1_PADDING);
            if ($encryptionOk === false) {
                return false;
            }//also you can return and error. If too big this will be false
            $encrypted .= $partialEncrypted;
        }
        return base64_encode($encrypted);//encoding the whole binary String as MIME base 64
    }


    /**
     * <p>
     * 公钥解密
     * </p>
     * decryptByPublicKey
     *
     * @param cipherText 已加密数据
     * @return
     */
    public function decrypt($cipherText)
    {
        $decrypted = '';
        $res = openssl_pkey_get_public(self::$publicKeyId);
        if(!$res){
            return $decrypted;
        }
        $detailArr = openssl_pkey_get_details($res);
        $decryptBlockSize = $detailArr['bits']/8;
        //decode must be done before spliting for getting the binary String
        $data = str_split(base64_decode($cipherText), $decryptBlockSize);
        foreach ($data as $chunk) {
            $partial = '';
            //be sure to match padding
            $decryptionOK = openssl_public_decrypt($chunk, $partial, self::$publicKeyId, OPENSSL_PKCS1_PADDING);var_dump(self::$publicKeyId, openssl_error_string());

            if ($decryptionOK === false) {
                return false;
            }//here also processed errors in decryption. If too big this will be false
            $decrypted .= $partial;
        }
        return $decrypted;
    }

    /**
     * 把数组所有元素排序，并按照“参数=参数值”的模式用“&”字符拼接成字符串
     *
     * @param params 需要排序并参与字符拼接的参数组
     * @return 拼接后字符串
     */
    private function createLinkString(Array $params)
    {
        $result = '';
        if (!$params) {
            return null;
        }
        $params = $this->paraFilter($params);
        if($params) {
            ksort($params);
            foreach ($params as $k => $v) {
                $result .= $k . '=' . $v . '&';
            }
            $result = substr($result, 0, -1);
        }
        return $result;
    }

    /**
     * 除去数组中的空值和签名参数
     *
     * @param oriMap 签名参数
     * @return 去掉空值与签名参数后的新签名参数组
     */
    private function paraFilter(Array $oriMap)
    {
        $result = array();
        if (!$oriMap) {
            return null;
        }
        foreach ($oriMap as $k => $v) {
            if (strcasecmp($k, self::SIGN) == 0
                || strcasecmp($k, self::SIGN_TYPE) == 0 || $v == '' || $v == null) {
                continue;
            }
            $result[$k] = $v;
        }
        return $result;
    }
}
