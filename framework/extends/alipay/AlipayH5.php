<?php

include("AopSdk.php");
require_once(FRAMEWORK_PATH . 'extends/LoggerHelper.php');

class AlipayH5
{
    protected $aop;
    protected $error;
    protected $status;
    protected $user_id;
    protected $logger;

    public function __construct($appId = '2018112362303497')
    {
        $appIdList = ['2018110862111199', '2018110661976912', '2018112362303497', '2018112662286976', '2018120462439121', '2018121262550151'];
        $this->aop = new AopClient();
        $this->aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';

        $this->aop->appId = (in_array($appId, $appIdList)) ? $appId : '2018112362303497';

        $this->aop->rsaPrivateKey = 'MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQCnsu0wn61YL3Th5y9SPVNHURi0XwpKUGD6eSZHeiuZ9qjOmu79aQ39XsB4DoFeZ7iaoVnvMrZwtPTvxknZg68RpeaXnCWqkx30W26gW+hVZZB57y3UbZkQDl4xy9b/r7kJdMzo0u6gncgdnUudSUTroTLfZh53ch67z6EqUX9Bj9od3WQnckaYEnoRLEwxKjBLy8M7RgMp3CimWLkp86yRjO6QqdQywAnnvX0mVOFf01Gs/AB/3d178TE6PtYVSRaqL5U0ppXNH28KK0sofIpKjVOU+sWBHVuEVHRUkspTjvFv2mOc9X+nsbwpZWFj4QEHOa2iQeiOgUGVnpC/8msLAgMBAAECggEAV4Mv8+ff9d0OCbUzJJ+MDfNsCPRv0kgP06XVLAe9KSNnBCol/WgNPONtXTl0mWdXFpqM7B5yxm4oQ9geQbxOZ89Dfmql3VXYk+QC3vwXSjkuI/OE3w4yigZ1cVcGY3e4AA9Lv1QT4w1zmMC07OeHZ88/VQVdcMfE8g1v9T2CQxuOVY6ReENfaavsm0l3hVCZphFbd8omMazVRMRacIn+x5e85JnVCIWxcpwaNedUQYMKK5WmQKdBanfGvSsp8WbLqc3krOOIkwVH9NqbpsHpttkwvBOAjuerYBy3vX0UfDF4IR9uGTy33TXEl/PqFa7L6GJxaXrsNJJ51Yne1BfKwQKBgQDTBRnCzS5qiIBrfcRzs+8q6GQPsMatjqsoX/hCNaxM35tg4EJagY5yH8062Oel9D8lIGLCTdJctbcNELlxZbjxAinHzz0BkAUGQGZFUFaM2hyKfTMNvpGKS6IxPwK7h8+nZAtvSXWSU7gmJANf5+IObNhCn7CXL8LexQIiHfbE7QKBgQDLceSxaPXGSRfZOdXFGKBKgNhUcCqsxG2cUXFq4Y/ls5JOTONcc50RdglvBSLmre86gyNwczdhvfRPAF3/NbIQGA1b5Z7VhaM4RFS9BC5AFVYzWIq44TRe91z9XSWSK3Skgo9N91GRwJ7Zhrn1b5fv2sRkJ2xT1D583SilQuYo1wKBgQCspu614NTKW1bfG+7BUAYuUCeWYueblzBY/3SLD4ki+I0TjUkc7gWTQIvVSyT1Nkr34HCNU8j7C75ylS11J2pS3pc6oUfj4GcL/2Lt8VZvNgHGGbvM0hAYW9ufeVOOBgeTiJqGek8U4yS3KB4OuRXPAaVLlYaRnIVPaVdefK+r3QKBgQCxBj+a59vEV+G6kQqj4BPKAGc8wgVAJAPEm1F3USJnG2PZYioMTkWD5hO7WNrPotWhMm7p8Ddmg2VMQOOJqG1yd5tYNWuKHCi0UzDw7+xWsro5H3hF+yAY6mEtzZldoRZz928+xk9h5hvS59pz6FBq0w9EntEx+GMPP1mYw6eGLQKBgQDHTdXEkpZOK07Oe5auZ4ny+19BKev0bA2BIcTYKZCueAwR6wTK/A8HBC+sEzexji9vguemcEDwxZLuWNW+wOx1dt0lFgjdtBvPIsY6vRU4VsS7pepBmdYMOOg2rWHO1d7l+f+abpH/gFH5vMz5PCdrSInl95T0F4xTge966CA4bw==';

        if ($appId == '2018112362303497' || $appId == '2018112662286976') {
            $this->aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAoZqF4PjFsu7aj0CbWa04kmXJiytdUPlDWcAfxSkn7JkYEbgRjLsEHEhWBleVOed+40q1sQnc9UggovA7V9BoZGMYcE5Iyur91jh1TfdHhlKTKOfx2/0+iw+j08vBjRuFu9l2c85KmZuD4UhmH13XpzfaWc40EZKB/lbUKss5pC3KHcnszvZKY8hGrSQQHDpqQnPX3tIkW0Ehu4X5d+0TWKJpoplf97US71gMh8NGWbP4emTA9nyFSAXNcSNcdF8Cais6+a3tdP7mvR8fanIdWan10OnZkcjhnSqxBhSGCUF+8EF0EzuPXBus7AG2greNXCIb5WL5VXnOOYmmmFVaPQIDAQAB';
        } elseif ($appId == '2018120462439121' || $appId == '2018121262550151'){
            $this->aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAnMfG+l/3qW7v9KcYlIZvSlXMf3RvSBT0yKZWeqNMM65Gqdq6m9CMa79lWNEJBKs/3DzLrJmT1G9n12GKzq1oFDjqmYpl9LWUv/JizsBMHTV//fNKiEBP576lA+J+XvRCmuOfce0+XiUNevaLRQKBEeoQsahUI4dPghulG2m7DuQE24P4MUpewdtvN4DxhVzmssOsnLH2MhYTVNdDNXt4e64kBDMg744kb+g5Kxsmy7K7KsnoO55Hl89HQ0L9LFrH0Jbp+I3OPZgcDZasZlXQ7hIwExEzjZ97wDhzrBFu4FSmXDoK3K+MJCIEjqE56UwEWzBr7LXS/f/Sv/l/wxvimQIDAQAB';
        } else {
            $this->aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAumUO5WZWzM9cTtfvAJti8a+4Cmr5hK+pAgHsfVAthIW8KDQEr4mg7ifYzNtyCxby9rAJakO1mzIe6L9+r1UcBEgjn+L9i1u2BN7Gng9uJ02yoZ1UV4TlG3ziDeSkOfHcs8U4pDR/bUBh/81oX0zvYdxU7MWLC9ib60aHHXGabPVTMSqopcg04RqycuLbFGx8TlNoXv0PZEHqfHHptXpKL1MnNcncbUHOddXOGvmKKkXVLzSvS5RwBPUNsq5z2bUtZrDyflPeEHnf7hIE/uKIp7Fr/doN8jZ2qdzxtgmic6LRTD2kTqRi6PhreU34+E6ZeuHNddHQi1kcCh05IsxR6QIDAQAB';
        }

        $this->aop->apiVersion = '1.0';
        $this->aop->signType = 'RSA2';
        $this->aop->postCharset = 'UTF-8';
        $this->aop->format = 'json';

        $this->logger = new LoggerHelper('alipay_user_id', IP_LOG);
    }

    // alipay.system.oauth.token(换取授权访问令牌)
    private function getSystemOauthToken($auth_code)
    {
        try {
            $request = new AlipaySystemOauthTokenRequest ();
            $request->setGrantType("authorization_code");
            $request->setCode($auth_code);
            $request->setRefreshToken("201208134b203fe6c11548bcabd8da5bb087a83b");
            $time_start = microtime(true);
            $result = $this->aop->execute($request);

            if (property_exists($result, 'alipay_system_oauth_token_response')
                && property_exists($result->alipay_system_oauth_token_response, 'user_id'))
            {
                $this->user_id = $result->alipay_system_oauth_token_response->user_id;
                $time = microtime(true) - $time_start;
                $this->logger->info(sprintf('[user_id: %s] 执行时间: %s', $this->user_id, $time));
                $this->status = true;
                return true;
            }

            // error
            if (property_exists($result, 'alipay_system_oauth_token_response')) {
                $this->error = $result->alipay_system_oauth_token_response->sub_msg;
                return false;
            }
            if (property_exists($result, 'error_response')) {
                $this->error = $result->error_response->sub_msg;
                return false;
            }
        } catch (Throwable $t) {
            $this->error = $t->getMessage();
            return false;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }

    }

    /**
     * alipay.user.info.share(支付宝会员授权信息查询接口)
     *
     * @param $access_token 位於 alipay_system_oauth_token_response
     *
     * ref: https://docs.open.alipay.com/api_9/alipay.system.oauth.token/
     */
    private function getUserInfo($access_token)
    {
        $request = new AlipayUserInfoShareRequest ();

        $result = $this->aop->execute($request, $access_token);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        if (property_exists($result, $responseNode) && property_exists($result->$responseNode, 'user_id')) {
            return $result->$responseNode->user_id;
        }

        // error
        if (property_exists($result, $responseNode)) {
            return $result->$responseNode->sub_msg;
        }
        if (property_exists($result, 'error_response')) {
            return $result->error_response->sub_msg;
        }
        return '';
    }

    private function log_e($tid)
    {
        $msg = sprintf('[tid: %s] %s', $tid, $this->error);
        $this->logger->error($msg);
    }
    public function getUserId($auth_code, $tid)
    {
        $this->status = false;
        $this->error = '';
        $this->user_id = '';
        $this->getSystemOauthToken($auth_code);
        if (!$this->status) {
            $this->log_e($tid);
        }

        // 尝试捞取多次
        for ($i = 0; $i <= 1; $i++) {
            if ($this->status) {
                break;
            }
            // 0.5 sec
            usleep(500000);
            $this->getSystemOauthToken($auth_code);
            if (!$this->status) {
                $this->log_e($tid);
            }
        }


        return [
            'status' => $this->status,
            'error' => $this->error,
            'user_id' => $this->user_id,
        ];
    }
}