<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 2018/4/12
 * Time: 下午 03:49
 */

class Md5
{
    public function md5sign($Md5key, $list, $ksort = 'on', $strtoupper = 'on',$key='key=' , $and = false)
    {
        if($ksort == 'on')
            ksort($list);

        $md5str = "";

        foreach($list as $k => $v) {
            if("" != $v && "sign" != $k && "key" != $k) {
                $md5str .= $k . "=" . $v . "&";
            }
        }
        if($and){
            $md5str = substr( $md5str, 0, -1 );
        }
        if($strtoupper == 'on'){
            $sign = strtoupper(md5($md5str . "$key" . $Md5key));
        }else{
            $sign = md5($md5str . "$key" . $Md5key);
        }

        return $sign;
    }
}