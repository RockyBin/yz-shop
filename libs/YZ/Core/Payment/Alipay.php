<?php
namespace YZ\Core\Payment;
use YZ\Core\Constants;
use Illuminate\Support\Facades\Cache;
class Alipay
{
    private $app_id = '';
    private $alipay_public_key = '';
    private $alipay_private_key = '';
    private $return_url = '';
    private $notify_url = '';
    private $aop_client = null;
    private $usesanbox = 0;
    
    /**
     * 初始化支付宝支付对象
     * @param type $app_id 开放平台的appid
     * @param type $alipay_public_key 支付宝公钥
     * @param type $alipay_private_key 用户的APP私钥
     * @param type $return_url 支付回调地址
     * @param type $notify_url 支付通知地址
     * @param int  $useSandbox 是否使用沙箱模式
     */
    public function __construct($app_id, $alipay_public_key, $alipay_private_key, $return_url, $notify_url, $useSandbox = 0)
    {
        $this->usesanbox = $useSandbox;

        $this->app_id = $app_id;
        $this->alipay_public_key = $alipay_public_key;
        $this->alipay_private_key = $alipay_private_key;
        $this->return_url = $return_url;
        $this->notify_url = $notify_url;

        //sanbox account
//        if ($this->usesanbox) {
//            $this->app_id = '2016092100560129';
//            $this->alipay_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDIgHnOn7LLILlKETd6BFRJ0GqgS2Y3mn1wMQmyh9zEyWlz5p1zrahRahbXAfCfSqshSNfqOmAQzSHRVjCqjsAw1jyqrXaPdKBmr90DIpIxmIyKXv4GGAkPyJ/6FTFY99uhpiq0qadD/uSzQsefWo0aTvP/65zi3eof7TcZ32oWpwIDAQAB';
//            $this->alipay_private_key = 'MIIEpAIBAAKCAQEA5gikTLSD6pTOXuq7SaY0Brk1qSL1YkMqs90cXI3IHgr2hnanMDNGxIGJZMGKKISyYF1LXfIO8BWQxoDUCmX6EAdZJ+55J9Xl143e+dpvzHcpi7VPbnIrTegVrxM0tBJ/ysdOhoLEs6204FLpcXsl2Y3LKW6+pET+sRip91PAIEBOWahIqvRFAGp9o93Xc8iDymFucs4waeqPzlmwBswzlDJFFSYnYqQVVwF4rDLqoxuoajhYoAHQxe4TNb18xg3CbFxFP5uzBNkqHoPXe7khVkEiRKEy9yYv6wummhoNp3qPFlEbaD2ary9x9UvyvQvm5ZQhEZ+LFsH8uTqiSHfM0wIDAQABAoIBAFjRDV7wc96nBedwClAtc/kEmctsTAJcnKhFvyWdOJ8g7H6OYY8ivTgyK7JTZ9ytH5JFc0waodng+b0rELPTG/IEZFAeq3jOBahshqNBy9jOSaQ/pSOnwUCbU4P9jmPYoK7StWcKJpiZgTT7zlaajcqqDL86mzEh0pTeSQHNvGi2r/7APYAriIAK/k11RQ4zJZ2w3zi9lOye1tgCGVqwR3gpcA1T3j2i9Kli0rgCrJuiiMKOqwShzvj5AO5fv3CnRW99//Xw/W2knxJ1EQwapeyHONftE0Kdb/EIg+WuYxOeLibODERPYXOLS3/CKWCAY8rqQ2puy4anllTEYqkyaEECgYEA/IylCdlnIBs/RcjPYZ+PvUq4oQHCrUKtO6a8Vz5fZw/IHbFB/eEmdfB2thy/5PbD5rbugrXf0GGIDkvTM84vYR0WPUNimZADdMqzyE8PUHpCyRG1J6WUCIQsmBiPBO+iqE+SlrV44h2KIBGhqLdOqkYB8n9Pqr7SRsAPMmYcLWUCgYEA6S0+NF+qCAu9Z68nM4Hocs10ObenQadnYVvjv0e4c7nncLsE/GjLDhOrQTI81FHIx46hWgykweGv+auBvPH5MG/bHtmnYYDK5Wmfn8uAwsjr6/G1dhN8b++ROjrINsAWCtLC25l6xK4BmK1hhDA2b2rmxthhTFZysgtaTf8eqdcCgYEA45v6TiMinzwPTVyLEwfUaxyBw5Irmy2BpUZDbjmnj+IYUDJmMGKP4DFlPAIzLC7+Jdvun908priP/5p08ba82sB1P6eQoKe7hbH+T+R4/+YAdOjBpMbE4NwGuNlBZIh4x0pX6f4JwXgv+XEKil0Sx8EqlhwJd/Bc4SjNSXXfpUUCgYEAzqxzPiispIUDVCtDK7wxM9A2/BF0BhVC5GB19My1CJ32LU0WlkKr98YnPJoyoF39ACPDj/U080P+neUOEVLH886xAR8Z5KorLDv6Z8AQWJWNxotus0GCQhStPFdtrlmDMASvAcV/s2QnthO3I1s4ZHj0I7sWQns9HeJCIG/H1fECgYBcNyQ8DsKg2mfbcIYyzsZqfW7l4Dud6AwC1jhnS1Ic8byLmuv9reXUERLOGfnBP7vqttzILWPHWly7qWsGpUIsdUX4mwWBOCU/J/yogqSQV/L+VCNAKqsDNRlXSMiVb/8qMU8jlNuVcAZXQRH+eC9OCTj6ojhBX24qhojgriht9g==';
//        }

        $this->aop_client = new \AopClient();
        $this->aop_client->gatewayUrl = "https://openapi.".($this->usesanbox ? 'alipaydev':'alipay').".com/gateway.do";
        $this->aop_client->appId = $this->app_id;
        $this->aop_client->rsaPrivateKey = $this->alipay_private_key; //应用私钥
        $this->aop_client->alipayrsaPublicKey = $this->alipay_public_key; //支付宝公钥
        $this->aop_client->apiVersion = '1.0';
        $this->aop_client->signType = $this->usesanbox ? 'RSA2' : 'RSA2';
        $this->aop_client->postCharset = 'utf-8';
        $this->aop_client->format = 'json';
    }

    /**
     * 发起支付
     * @param $orderId 订单ID
     * @param $memberId 会员ID
     * @param $amount 支付金额，单位分
     * @param $iswap 是否手机端
     * @param $callback 支付成功的回调
     * @return string|\提交表单HTML文本
     * @throws \Exception
     */
    public function doPay($orderId,$memberId,$amount,$iswap,$callback)
	{
	    $callbackKey = md5($callback);
        Cache::set($callbackKey,$callback,3600);
		$return_url = $this->return_url.'/'.$orderId.'/'.$memberId.'/'.urlencode(base64_encode($callbackKey));
		$notify_url = $this->notify_url.'/'.$orderId.'/'.$memberId.'/'.urlencode(base64_encode($callbackKey));
		//dd($notify_url);
		if($iswap) $request = new \AlipayTradeWapPayRequest ();
		else $request = new \AlipayTradePagePayRequest ();
		$request->setReturnUrl($return_url);  
		$request->setNotifyUrl($notify_url);
		$payinfo = [
			'product_code' => 'FAST_INSTANT_TRADE_PAY',
			'out_trade_no' => $orderId,
			'subject' => '支付订单'.$orderId,
			'total_amount' => sprintf("%.2f",round($amount/100,2)),
			'body' => '支付订单'.$orderId
		];
		$request->setBizContent(json_encode($payinfo,JSON_UNESCAPED_UNICODE));

		//请求
		$result = $this->aop_client->pageExecute($request);

		//输出  
		return $result;
	}

    /**
     * 检测支付宝回调
     * @return bool|PaymentResult
     */
    public function checkReturn()
    {
        try {
            $flag = $this->aop_client->rsaCheckV1($_GET, NULL, $this->aop_client->signType);
            \YZ\Core\Logger\Log::writeLog("alipay-return", "flag = $flag,data = ".var_export($_GET,true));
            if($flag){
                $success = true;
                $amount = round(floatval($_GET['total_amount']),2) * 100; //转换为分
                $tradeno = $_GET['trade_no'];
                $orderid = $_GET['out_trade_no'];
                $paytype = \YZ\Core\Constants::PayType_Alipay;
                return new PaymentResult($success,$tradeno,$orderid,$amount,$paytype,null,$_GET);
            }
        }catch (\Exception $ex){
            \YZ\Core\Logger\Log::writeLog("alipay-return", "error: ".$ex->getMessage());
        }
        return false;
    }

    /**
     * 检测支付宝异步通知
     * @return bool|PaymentResult
     */
	public function checkNotify()
    {
        try {
            $flag = $this->aop_client->rsaCheckV1($_POST, NULL, $this->aop_client->signType);
            \YZ\Core\Logger\Log::writeLog("alipay-notify", "flag = $flag,data = ".var_export($_POST,true));
            if($flag){
	            $success = $_POST['trade_status'] == 'TRADE_SUCCESS';
                $amount = round(floatval($_POST['total_amount']),2) * 100; //转换为分
                $tradeno = $_POST['trade_no'];
                $orderid = $_POST['out_trade_no'];
                $paytype = \YZ\Core\Constants::PayType_Alipay;
                return new PaymentResult($success,$tradeno,$orderid,$amount,$paytype,null,$_POST);
        	}else{
	        	echo 'error';
        	}
        }catch (\Exception $ex){
            \YZ\Core\Logger\Log::writeLog("alipay-notify", "error: ".$ex->getMessage());
            echo 'error';
        }
        return false;
    }

    /**
     * 支付宝退款
     * @param string $transactionId 原支付的交易号
     * @param string $refundNumber 退款单号，由商户生成
     * @param int $totalFee 原支付的金额
     * @param int $refundFee 要退款的金额(单位分)
     * @param array $config 其它配置，如 ['refund_desc' => '商品已售完']
     * @return RefundResult
     */
    public function refund(string $transactionId, string $refundNumber, int $totalFee, int $refundFee, array $config = []){
        $request = new \AlipayTradeRefundRequest ();
        //$request->setReturnUrl($return_url);
        //$request->setNotifyUrl($notify_url);
        $payinfo = [
            'out_trade_no' => '',
            'trade_no' => $transactionId,
            'refund_amount' => sprintf("%.2f",round($refundFee/100,2)),
            'out_request_no' => $refundNumber,
            'refund_reason' => $config['refund_reason']
        ];
        $request->setBizContent(json_encode($payinfo,JSON_UNESCAPED_UNICODE));

        //请求
        $res = $this->aop_client->execute($request);
        $res = $res->alipay_trade_refund_response;

        if($res->code == '10000'){
            $result = new RefundResult();
            $result->orderid = $res->out_trade_no;
            $result->amount = intval($res->refund_fee * 100);
            $result->tradeno = $transactionId;
            $result->paytype = \YZ\Core\Constants::PayType_Alipay;
            $result->apidata = $res;
            $result->refundNo = $refundNumber;
            $result->refundTradeNo = $refundNumber;
            $result->success = true;
            return $result;
        }else{
            \YZ\Core\Logger\Log::writeLog("alipay-refund", "error: ".var_export($res,true));
            throw new \Exception($res->sub_msg);
        }
    }

    /**
     * 用企业付款接口付款给用户，目前主要用于微信提现
     * @param string $userid 用户的支付宝ID
     * @param $money 付款金额(单位：分)
     * @param $about 付款备注信息
     * @return PaymentResult
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function payToUser(string $userid,$money,$about){
        $request = new \AlipayFundTransToaccountTransferRequest();
        $payinfo = [
            'out_biz_no' => "tx".date('YmdHis').getMillisecond(false),
            'payee_type' => 'ALIPAY_USERID',
            'payee_account' => $userid,
            'amount' => sprintf("%.2f",round($money/100,2)),
            'remark' => $about,
        ];
        $request->setBizContent(json_encode($payinfo,JSON_UNESCAPED_UNICODE));
        //请求
        $res = $this->aop_client->execute($request);
        $res = (array)$res->alipay_fund_trans_toaccount_transfer_response;
        if($res['code'] == '10000'){
            $success = true;
            $amount = $money;
            $tradeno = $res['order_id'];
            $orderid = $res['out_biz_no'];
            $paytype = \YZ\Core\Constants::PayType_Alipay;
            $result = new PaymentResult($success, $tradeno, $orderid, $amount, $paytype,['userid' => $userid],$res);
            return $result;
        }else{
            \YZ\Core\Logger\Log::writeLog("alipay-payToUser", "error: ".var_export($res,true));
            throw new \Exception($res['msg'].$res['sub_msg']);
        }
    }

    /**
     * 用企业付款接口付款给用户，目前主要用于支付宝提现
     * @param array alipay_account 用户的支付宝手机号或邮箱 alipay_name用户的支付宝姓名
     * @param $money 付款金额(单位：分)
     * @param $about 付款备注信息
     * @return PaymentResult
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function payToUserByAccount(array $account,$money,$about){
        $request = new \AlipayFundTransToaccountTransferRequest();
        $payinfo = [
            'out_biz_no' => "tx".date('YmdHis').getMillisecond(false),
            'payee_type' => 'ALIPAY_LOGONID',
            'payee_account' => $account['alipay_account'],
            'payee_real_name'=>$account['alipay_name'],
            'amount' => sprintf("%.2f",round($money/100,2)),
            'remark' => $about,
        ];
        $request->setBizContent(json_encode($payinfo,JSON_UNESCAPED_UNICODE));
        //请求
        $res = $this->aop_client->execute($request);
        $res = (array)$res->alipay_fund_trans_toaccount_transfer_response;
        if($res['code'] == '10000'){
            $success = true;
            $amount = $money;
            $tradeno = $res['order_id'];
            $orderid = $res['out_biz_no'];
            $paytype = \YZ\Core\Constants::PayType_Alipay;
            $result = new PaymentResult($success, $tradeno, $orderid, $amount, $paytype,['userid' => $userid],$res);
            return $result;
        }else{
            \YZ\Core\Logger\Log::writeLog("alipay-payToUser", "error: ".var_export($res,true));
            throw new \Exception($res['msg'].$res['sub_msg']);
        }
    }

    /**
     * 查询转帐状态
     * @param $tradeno 支付宝转帐的交易号
     */
    public function transOrderQuery($tradeno){
        $request = new \AlipayFundTransOrderQueryRequest();
        $payinfo = [
            'order_id' => $tradeno,
        ];
        $request->setBizContent(json_encode($payinfo,JSON_UNESCAPED_UNICODE));
        //请求
        $res = $this->aop_client->execute($request);
        print_r($res);
    }
}