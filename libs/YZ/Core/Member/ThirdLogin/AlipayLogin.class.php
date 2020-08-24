<?php

namespace YZ\Core\Member\ThirdLogin;

class AlipayLogin
{
    private $app_id = '';
    private $alipay_public_key = '';
    private $alipay_private_key = '';
    private $usesanbox = false;

    public function __construct($app_id, $alipay_public_key, $alipay_private_key)
    {
        $this->usesanbox = config('app.ALIPAY_LOGIN_SANDBOX') === 'true';

        $this->app_id = $app_id;
        $this->alipay_public_key = $alipay_public_key;
        $this->alipay_private_key = $alipay_private_key;

        //sanbox account
        if ($this->usesanbox) {
            $this->app_id = '2016092100560129';
            $this->alipay_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDIgHnOn7LLILlKETd6BFRJ0GqgS2Y3mn1wMQmyh9zEyWlz5p1zrahRahbXAfCfSqshSNfqOmAQzSHRVjCqjsAw1jyqrXaPdKBmr90DIpIxmIyKXv4GGAkPyJ/6FTFY99uhpiq0qadD/uSzQsefWo0aTvP/65zi3eof7TcZ32oWpwIDAQAB';
            $this->alipay_private_key = 'MIIEpAIBAAKCAQEA5gikTLSD6pTOXuq7SaY0Brk1qSL1YkMqs90cXI3IHgr2hnanMDNGxIGJZMGKKISyYF1LXfIO8BWQxoDUCmX6EAdZJ+55J9Xl143e+dpvzHcpi7VPbnIrTegVrxM0tBJ/ysdOhoLEs6204FLpcXsl2Y3LKW6+pET+sRip91PAIEBOWahIqvRFAGp9o93Xc8iDymFucs4waeqPzlmwBswzlDJFFSYnYqQVVwF4rDLqoxuoajhYoAHQxe4TNb18xg3CbFxFP5uzBNkqHoPXe7khVkEiRKEy9yYv6wummhoNp3qPFlEbaD2ary9x9UvyvQvm5ZQhEZ+LFsH8uTqiSHfM0wIDAQABAoIBAFjRDV7wc96nBedwClAtc/kEmctsTAJcnKhFvyWdOJ8g7H6OYY8ivTgyK7JTZ9ytH5JFc0waodng+b0rELPTG/IEZFAeq3jOBahshqNBy9jOSaQ/pSOnwUCbU4P9jmPYoK7StWcKJpiZgTT7zlaajcqqDL86mzEh0pTeSQHNvGi2r/7APYAriIAK/k11RQ4zJZ2w3zi9lOye1tgCGVqwR3gpcA1T3j2i9Kli0rgCrJuiiMKOqwShzvj5AO5fv3CnRW99//Xw/W2knxJ1EQwapeyHONftE0Kdb/EIg+WuYxOeLibODERPYXOLS3/CKWCAY8rqQ2puy4anllTEYqkyaEECgYEA/IylCdlnIBs/RcjPYZ+PvUq4oQHCrUKtO6a8Vz5fZw/IHbFB/eEmdfB2thy/5PbD5rbugrXf0GGIDkvTM84vYR0WPUNimZADdMqzyE8PUHpCyRG1J6WUCIQsmBiPBO+iqE+SlrV44h2KIBGhqLdOqkYB8n9Pqr7SRsAPMmYcLWUCgYEA6S0+NF+qCAu9Z68nM4Hocs10ObenQadnYVvjv0e4c7nncLsE/GjLDhOrQTI81FHIx46hWgykweGv+auBvPH5MG/bHtmnYYDK5Wmfn8uAwsjr6/G1dhN8b++ROjrINsAWCtLC25l6xK4BmK1hhDA2b2rmxthhTFZysgtaTf8eqdcCgYEA45v6TiMinzwPTVyLEwfUaxyBw5Irmy2BpUZDbjmnj+IYUDJmMGKP4DFlPAIzLC7+Jdvun908priP/5p08ba82sB1P6eQoKe7hbH+T+R4/+YAdOjBpMbE4NwGuNlBZIh4x0pX6f4JwXgv+XEKil0Sx8EqlhwJd/Bc4SjNSXXfpUUCgYEAzqxzPiispIUDVCtDK7wxM9A2/BF0BhVC5GB19My1CJ32LU0WlkKr98YnPJoyoF39ACPDj/U080P+neUOEVLH886xAR8Z5KorLDv6Z8AQWJWNxotus0GCQhStPFdtrlmDMASvAcV/s2QnthO3I1s4ZHj0I7sWQns9HeJCIG/H1fECgYBcNyQ8DsKg2mfbcIYyzsZqfW7l4Dud6AwC1jhnS1Ic8byLmuv9reXUERLOGfnBP7vqttzILWPHWly7qWsGpUIsdUX4mwWBOCU/J/yogqSQV/L+VCNAKqsDNRlXSMiVb/8qMU8jlNuVcAZXQRH+eC9OCTj6ojhBX24qhojgriht9g==';
        }
    }

    public function getLoginUrl($redirectUrl){
        //应用的APPID
        $app_id = $this->app_id;
        //state参数用于防止CSRF攻击，成功授权后回调时会原样带回
        $_SESSION['alipay_state'] = md5(uniqid(rand(), true));
        //拼接请求授权的URL
        $url = "https://openauth.".($this->usesanbox ? 'alipaydev':'alipay').".com/oauth2/publicAppAuthorize.htm?app_id=".$app_id."&scope=auth_user&redirect_uri=".$redirectUrl."&state=".$_SESSION['alipay_state'];
        return "<script> window.top.location.href='" . $url . "'</script>";
    }

    public function getUserInfo($auth_code)
    {
        $aop = new \AopClient();
        $aop->gatewayUrl = "https://openapi.".($this->usesanbox ? 'alipaydev':'alipay').".com/gateway.do";
        $aop->appId = $this->app_id;
        $aop->rsaPrivateKey = $this->alipay_private_key; //应用私钥
        $aop->alipayrsaPublicKey = $this->alipay_public_key; //支付宝公钥
        $aop->apiVersion = '1.0';
        $aop->signType = $this->usesanbox ? 'RSA' : 'RSA2';
        $aop->postCharset = 'utf-8';
        $aop->format = 'json';

        //根据返回的auth_code换取access_token
        $request = new \AlipaySystemOauthTokenRequest();
        $request->setGrantType("authorization_code");
        $request->setCode($auth_code);
        $result = $aop->execute($request);
        $access_token = $result->alipay_system_oauth_token_response->access_token;
        //Step3: 用access_token获取用户信息
        $request = new \AlipayUserInfoShareRequest();
        $result = $aop->execute($request, $access_token);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if (!empty($resultCode) && $resultCode == 10000) {
            $user_data = $result->$responseNode;
            return (array)$user_data;
        }else{
            throw new \Exception('get alipay userinfo fail: '.json_encode($result->$responseNode,JSON_UNESCAPED_UNICODE));
        }
	}
}
