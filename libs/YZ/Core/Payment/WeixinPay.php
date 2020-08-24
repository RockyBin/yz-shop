<?php

namespace YZ\Core\Payment;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use EasyWeChat\Factory;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;

class WeixinPay
{
    private $mid = ''; //商户号
    private $appkey = ''; //微信支付的key
    private $appid = ''; //发起微信支付的相关appid,如公众号的appid、小程序的appid等
    private $notify_url = '';
    private $return_url = '';
    private $useSandbox = false;
    private $cert_path = '';
    private $key_path = '';
    private $is_service_mode = 0; //是否为服务商模式
    private $appType = 'no';

    /**
     * 初始化微信支付对象
     * @param string $mid 商户号
     * @param string $appkey 支付秘钥
     * @param string $appid 发起支付的APPID
     * @param string $return_url 支付回调地址
     * @param string $notify_url 支付通知地址
     * @param string $cert_path API证书路径
     * @param string $key_path API证书秘钥路径
     */
    public function __construct($mid, $appkey, $appid, $return_url, $notify_url, $cert_path = '', $key_path = '', $is_service_mode = 0,$appType = 'no')
    {
        $this->useSandbox = config('app.WEIXIN_PAY_SANDBOX') === 'true';

        $this->mid = $mid;
        $this->appkey = $appkey;
        $this->appid = $appid;
        $this->return_url = $return_url;
        $this->notify_url = $notify_url;
        // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
        if (file_exists($cert_path)) $this->cert_path = $cert_path; // XXX: 绝对路径！！！！
        if (file_exists($key_path)) $this->key_path = $key_path; // XXX: 绝对路径！！！！
        $this->is_service_mode = $is_service_mode;
		if(!$appType) $appType = 'no'; //注意这里不能为空，否则回调URL会以/结束，这时坑爹的apache会自动301一下，导致检测回调不成功
        $this->appType = $appType;

        //沙箱测试的密钥 一个原则： 支付沙箱的金额必须是用例中指定的金额，也就是 1.01 元，1.02元等，不能是你自己的商品的实际价格，必须是这个数
        if ($this->useSandbox) $this->appkey = '81a09a86e7a3673317c0d1a284477cd4';
    }

    public function getSandboxInfo()
    {
        $options = [
            'app_id' => $this->appid,
            'key' => $this->appkey,
            'mch_id' => $this->mid
        ];

        $payment = Factory::payment($options); //微信支付
        $result = $payment->sandbox->getKey();
        return $result;
    }

    private function getCommonOptions($addtionalOps = []){
        $options = [
            'app_id' => $this->appid,
            'key' => $this->appkey,
            'mch_id' => $this->mid,
            'sandbox' => $this->useSandbox
        ];
        if($addtionalOps['is_service_mode']){
            unset($addtionalOps['is_service_mode']);
            $addtionalOps['app_id'] = 'wx8aaf5bd2d164ad02';
            $addtionalOps['key'] = '3a715f23ff6f28d00b811887386f7c20';
            $addtionalOps['mch_id'] = '1558269391';
            $addtionalOps['sub_appid'] = $this->appid;
            $addtionalOps['sub_mch_id'] = $this->mid;
            if($addtionalOps['cert_path']) $addtionalOps['cert_path'] = base_path().'/data/WeixinPayService/apiclient_cert.pem';
            if($addtionalOps['key_path']) $addtionalOps['key_path'] = base_path().'/data/WeixinPayService/apiclient_key.pem';
        }
        return array_merge($options, $addtionalOps);
    }

    /**
     * PC端二维码支付
     * @param $orderId 订单号
     * @param $memberId 会员号
     * @param $amount 金额（单位：分）
     * @param $callback 支付入帐成功后的回调
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function doPayWithQrCode($orderId, $memberId, $amount, $callback)
    {
        $notify_url = $this->notify_url . '/' . $orderId . '/' . $memberId . '/' . urlencode(base64_encode($callback)).'/'.$this->appType;
        $options = $this->getCommonOptions(['notify_url' => $notify_url,'is_service_mode' => $this->is_service_mode]);
        $payment = Factory::payment($options); //微信支付
        $result = $payment->order->unify([
            'sign_type' => 'MD5',
            'body' => '支付订单' . $orderId,
            'out_trade_no' => $this->generateOutTradeNo($orderId),
            'total_fee' => $amount,
            'spbill_create_ip' => getClientIP(),
            'trade_type' => 'NATIVE',
            'product_id' => $orderId
        ]);
        if ($result['return_code'] == 'FAIL') throw new \Exception($result['return_msg']);
        $result['trade_type'] = 'NATIVE'; //当下单失败时，微信不会返回 trade_type 字段，会导致前台的一些判断比较麻烦，这里重设一下，方便前台判断
        //输出
        return $result;
    }

    /**
     * 手机浏览器H5支付
     * @param $orderId 订单号
     * @param $memberId 会员号
     * @param $amount 金额（单位：分）
     * @param $callback 支付入帐成功后的回调
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function doPayWithH5($orderId, $memberId, $amount, $callback)
    {
        $notify_url = $this->notify_url . '/' . $orderId . '/' . $memberId . '/' . urlencode(base64_encode($callback)).'/'.$this->appType;
        $options = $this->getCommonOptions(['notify_url' => $notify_url,'is_service_mode' => $this->is_service_mode]);
        $payment = Factory::payment($options); //微信支付
        $data = [
            'sign_type' => 'MD5',
            'body' => '支付订单' . $orderId,
            'out_trade_no' => $this->generateOutTradeNo($orderId),
            'total_fee' => $amount,
            'spbill_create_ip' => getClientIP(),
            'trade_type' => 'MWEB',
            'product_id' => $orderId
        ];
        $result = $payment->order->unify($data);
        Log::writeLog('weixinpay-unify',"统一下单结果:".var_export($options,true).var_export($data,true).var_export($result,true));
        if ($result['return_code'] == 'FAIL') throw new \Exception($result['return_msg']);
        $result['trade_type'] = 'MWEB'; //当下单失败时，微信不会返回 trade_type 字段，会导致前台的一些判断比较麻烦，这里重设一下，方便前台判断
        //输出
        return $result;
    }

    /**
     * 微信公众号内调用JSSDK进行支付
     * @param $orderId 订单号
     * @param $memberId 会员号
     * @param $amount 金额（单位：分）
     * @param $callback 支付入帐成功后的回调
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function doPayWithJSSDK($openid, $orderId, $memberId, $amount, $callback)
    {
        $notify_url = $this->notify_url . '/' . $orderId . '/' . $memberId . '/' . urlencode(base64_encode($callback)).'/'.$this->appType;
        $options = $this->getCommonOptions(['notify_url' => $notify_url,'is_service_mode' => $this->is_service_mode]);
        $payment = Factory::payment($options); //微信支付
        $data = [
            'sign_type' => 'MD5',
            'body' => '支付订单' . $orderId,
            'out_trade_no' => $this->generateOutTradeNo($orderId),
            'total_fee' => $amount,
            'spbill_create_ip' => getClientIP(),
            'trade_type' => 'JSAPI',
            'product_id' => $orderId
        ];
        if($this->is_service_mode) $data['sub_openid'] = $openid;
        else $data['openid'] = $openid;
        $result = $payment->order->unify($data);
        Log::writeLog('weixinpay-unify',"统一下单结果:".var_export($options,true).var_export($data,true).var_export($result,true));
        if ($result['return_code'] == 'FAIL') throw new \Exception($result['return_msg']);
        if ($result['result_code'] == 'FAIL') throw new \Exception($result['err_code_des']);
        $jssdk = $payment->jssdk;
        $json = $jssdk->bridgeConfig($result['prepay_id']);
        $config['trade_type'] = 'JSAPI'; //当下单失败时，微信不会返回 trade_type 字段，会导致前台的一些判断比较麻烦，这里重设一下，方便前台判断
        $config['json'] = $json;

        //输出
        return $config;
    }

    /**
     * 处理微信支付回调
     * @param string $transactionId 支付交易号
     * @return bool|PaymentResult
     */
    public function checkReturn($transactionId)
    {
        try {
            $options = $this->getCommonOptions(['is_service_mode' => $this->is_service_mode]);
            $payment = Factory::payment($options); //微信支付
            $res = $payment->order->queryByTransactionId($transactionId);
            \YZ\Core\Logger\Log::writeLog("weixinpay-return", "data = " . var_export($res, true));
            if ($res['result_code'] == 'SUCCESS' && $res['return_code'] == 'SUCCESS' && $res['trade_state'] == 'SUCCESS') {
                $success = true;
                $amount = $res['total_fee']; //转换为分
                $tradeno = $res['transaction_id'];
                $orderid = $this->parseOrderId($res['out_trade_no']);
                $paytype = \YZ\Core\Constants::PayType_Weixin;
                return new PaymentResult($success, $tradeno, $orderid, $amount, $paytype, null, $res);
            }
        } catch (\Exception $ex) {
            \YZ\Core\Logger\Log::writeLog("weixinpay-return", "error: " . $ex->getMessage());
        }
        return false;
    }

    /**
     * 处理微信支付异步通知
     * @return bool|null
     */
    public function checkNotify()
    {
        try {
            $options = $this->getCommonOptions(['is_service_mode' => $this->is_service_mode]);
            $payresult = null;
            $payment = Factory::payment($options);
            $response = $payment->handlePaidNotify(function ($notify) use (&$payresult) {
                \YZ\Core\Logger\Log::writeLog("weixinpay-notify", ",data = " . var_export($notify, true));
                if ($notify['result_code'] == 'SUCCESS' && $notify['return_code'] == 'SUCCESS') {
                    $success = $notify['result_code'] == 'SUCCESS';
                    $amount = $notify['total_fee'];
                    $tradeno = $notify['transaction_id'];
                    $orderid = $this->parseOrderId($notify['out_trade_no']);
                    $paytype = \YZ\Core\Constants::PayType_Weixin;
                    $openid = $this->is_service_mode ? $notify['sub_openid'] : $notify['openid'];
                    $payresult = new PaymentResult($success, $tradeno, $orderid, $amount, $paytype, ['openid' => $openid], $notify);
                } else {
                    throw new \Exception('weixin pay notify error');
                }
            });
            return $payresult;
        } catch (\Exception $ex) {
            \YZ\Core\Logger\Log::writeLog("weixinpay-notify", "error: " . $ex->getMessage());
            return false;
        }
        return false;
    }

    /**
     * 微信支付退款
     * @param string $transactionId 原支付的交易号
     * @param string $refundNumber 退款单号，由商户生成
     * @param int $totalFee 原支付的金额
     * @param int $refundFee 要退款的金额(单位分)
     * @param array $config 其它配置，如 ['refund_desc' => '商品已售完']
     * @return RefundResult
     */
    public function refund(string $transactionId, string $refundNumber, int $totalFee, int $refundFee, array $config = [])
    {
        $options = $this->getCommonOptions(['cert_path' => $this->cert_path,'key_path' => $this->key_path,'is_service_mode' => $this->is_service_mode]);
        $payment = Factory::payment($options);
        $res = $payment->refund->byTransactionId($transactionId, $refundNumber, $totalFee, $refundFee, $config);
        if ($res['err_code'] == 'NOTENOUGH') { //微信默认是用未结算帐户退款，当提示余额不足时，采用余额退款
            $defaults = ['refund_account' => 'REFUND_SOURCE_RECHARGE_FUNDS'];
            $config = array_merge($defaults, $config);
            //微信是默认使用未结算金额退款的，但微信的结算时间可能会比网站设置的维权时间短，这可能会造成未结算金额帐户余额不足，所以我们默认使用可用余额方式退款
            $res = $payment->refund->byTransactionId($transactionId, $refundNumber, $totalFee, $refundFee, $config);
        }
        if ($res['return_code'] == 'FAIL') {
            \YZ\Core\Logger\Log::writeLog("weixinpay-refund", "error: " . var_export($res, true));
            throw new \Exception($res['return_msg']);
        }
        if ($res['result_code'] == 'SUCCESS') {
            $result = new RefundResult();
            $result->orderid = $this->parseOrderId($res['out_trade_no']);
            $result->amount = $res['refund_fee'];
            $result->tradeno = $res['transaction_id'];
            $result->paytype = Constants::PayType_Weixin;
            $result->apidata = $res;
            $result->refundNo = $res['out_refund_no'];
            $result->refundTradeNo = $res['refund_id'];
            $result->success = true;
            return $result;
        } else {
            \YZ\Core\Logger\Log::writeLog("weixinpay-refund", "error: " . var_export($res, true));
            throw new \Exception($res['err_code_des']);
        }
    }

    /**
     * 用企业付款接口付款给用户，目前主要用于微信提现，此接口不支持服务商模式
     * @param string $openid 用户的openid
     * @param $money 付款金额(单位：分)
     * @param $about 付款备注信息
     * @return PaymentResult
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function payToUser(string $openid, $money, $about)
    {
        $options = $this->getCommonOptions(['cert_path' => $this->cert_path,'key_path' => $this->key_path]);
        $payment = Factory::payment($options);
        $res = $payment->transfer->toBalance([
            'partner_trade_no' => "tx" . date('YmdHis') . getMillisecond(false),
            'openid' => $openid,
            'check_name' => 'NO_CHECK',
            're_user_name' => '',
            'amount' => $money,
            'desc' => $about,
        ]);

        if ($res['result_code'] == 'SUCCESS') {
            $success = true;
            $amount = $money;
            $tradeno = $res['payment_no'];
            $orderid = $res['partner_trade_no'];
            $paytype = \YZ\Core\Constants::PayType_Weixin;
            $result = new PaymentResult($success, $tradeno, $orderid, $amount, $paytype, ['openid' => $openid], $res);
            return $result;
        } else {
            \YZ\Core\Logger\Log::writeLog("weixinpay-corppay", "error: " . var_export($res, true));
            throw new \Exception($res['err_code_des']);
        }
    }

    /**
     * 根据 orderId 生成 outTradeNo
     * @param $orderId
     * @return string
     */
    private function generateOutTradeNo($orderId)
    {
        if (empty($orderId)) {
            $orderId = generateOrderId();
        }
        return $orderId . '_' . randInt(1000, 9999);
    }

    /**
     * 根据 outTradeNo 分析 orderId
     * @param $outTradeNo
     * @return bool|string
     */
    private function parseOrderId($outTradeNo)
    {
        $index = strrpos($outTradeNo, '_');
        // 如果没有“_”，则认为整个是 orderId
        if ($index === false) {
            return $outTradeNo;
        }

        // 把最后一段“_”后的信息忽略
        return substr($outTradeNo, 0, $index);
    }
}