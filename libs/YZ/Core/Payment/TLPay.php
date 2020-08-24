<?php

namespace YZ\Core\Payment;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use EasyWeChat\Factory;
use YZ\Core\Constants;

/**
 * Class 通联支付接口
 * @package YZ\Core\Payment
 */
class TLPay
{
    private $mid = ''; //商户号
    private $appkey = ''; //密钥
    private $appid = ''; //通联支付APPID
    private $notify_url = '';
    private $return_url = '';
    private $union_api = "https://vsp.allinpay.com/apiweb/unitorder/pay"; //统一下单接口地址
    private $h5_api = "https://syb.allinpay.com/apiweb/h5unionpay/unionorder"; //H5收银台下单接口地址
    private $query_api = "https://vsp.allinpay.com/apiweb/unitorder/query"; //订单查询接口
    private $refund_api = "https://vsp.allinpay.com/apiweb/unitorder/refund";

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
    public function __construct($mid, $appkey, $appid, $return_url, $notify_url, $is_service_mode = 0)
    {
        $this->mid = $mid;
        $this->appkey = $appkey;
        $this->appid = $appid;
        $this->return_url = $return_url;
        $this->notify_url = $notify_url;
        $this->is_service_mode = $is_service_mode;
    }

    /**
     * 将参数数组签名
     */
    public static function SignArray(array $array,$appkey){
        $array['key'] = $appkey;// 将key放到数组中一起进行排序和组装
        ksort($array);
        $blankStr = static::ToUrlParams($array);
        $sign = md5($blankStr);
        return $sign;
    }

    public static function ToUrlParams(array $array)
    {
        $buff = "";
        foreach ($array as $k => $v)
        {
            if($v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 校验签名
     * @param array 参数
     * @param unknown_type appkey
     */
    public static function ValidSign(array $array, $appkey){
        $sign = $array['sign'];
        unset($array['sign']);
        $array['key'] = $appkey;
        $mySign = static::SignArray($array, $appkey);
        return strtolower($sign) == strtolower($mySign);
    }

    private function getCommonOptions($addtionalOps = []){
        $options = [
            'appid' => $this->appid,
            'cusid' => $this->mid,
        ];
        if($addtionalOps['is_service_mode']){ //暂时不支持合作方模式
            //$params["orgid"] = $orgid;
        }
        unset($addtionalOps['is_service_mode']);
        return array_merge($options, $addtionalOps);
    }

    /**
     * H5支付，用在微信公众号环境下
     * @param $orderId 订单号
     * @param $memberId 会员号
     * @param $amount 金额（单位：分）
     * @param $callback 支付入帐成功后的回调
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function doPayWithH5($orderId, $memberId, $amount, $callback)
    {
        $notify_url = $this->notify_url . '/' . $orderId . '/' . $memberId . '/' . urlencode(base64_encode($callback));
        $return_url = $this->return_url . '/' . $orderId . '/' . $memberId . '/' . urlencode(base64_encode($callback));

        // 生成签名字符串。
        $params = $this->getCommonOptions(['is_service_mode' => $this->is_service_mode]);
        $params["version"] = '12';
        $params["trxamt"] = $amount;
        $params["reqsn"] = $orderId;
        $params["charset"] = 'UTF-8';
        $params["returl"] = $return_url;
        $params["notify_url"] = $notify_url;
        $params["body"] = '支付订单'.$orderId;
        $params["remark"] = '';
        $params["randomstr"] = randString(10);
        $params["validtime"] = 5;
        $params["limit_pay"] = 'no_credit';
        $params["asinfo"] = '';
        //签名，设为signMsg字段值。
        $signMsg = static::SignArray($params,$this->appkey);//签名
        $form = '<form name="tlpay-form" action="'.$this->h5_api.'" method="post">';
        $form .= '<input type="hidden" name="sign" value="'.$signMsg.'" />';
        foreach ($params as $key => $val){
            $form .= '<input type="hidden" name="'.$key.'" value="'.$val.'" />';
        }
        $form .= "</form>";
        $form .= "<script>document.forms['tlpay-form'].submit();</script>";
        $result = [];
        $result['data'] = $form;
        $result['trade_type'] = 'FORM';
        return $result;
    }

    /**
     * 通联统一下单接口
     * @param $orderId 订单号
     * @param $memberId 会员号
     * @param $amount 金额（单位：分）
     * @param $callback 支付入帐成功后的回调
     * @param $payType 支付方式，详见 https://aipboss.allinpay.com/know/devhelp/main.php?pid=24
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function doPayWithUnion($orderId, $memberId, $amount, $callback, $payType, $acct = '')
    {
        $notify_url = $this->notify_url . '/' . $orderId . '/' . $memberId . '/' . base64_encode($callback);

        // 生成签名字符串。
        $params = $this->getCommonOptions(['is_service_mode' => $this->is_service_mode]);
        $params["version"] = '11';
        $params["trxamt"] = $amount;
        $params["reqsn"] = $orderId;
        $params["paytype"] = $payType;
        $params["randomstr"] = randString(10);
        $params["body"] = '支付订单'.$orderId;
        $params["remark"] = "";
        $params["acct"] = $acct;
        $params["limit_pay"] = "no_credit";
        $params["idno"] = "";
        $params["truename"] = "";
        $params["asinfo"] = "";
        $params["notify_url"] = $notify_url;
        $params["sign"] = static::SignArray($params,$this->appkey);

        $paramsStr = static::ToUrlParams($params);
        $url = $this->union_api;
        $rsp = $this->request($url, $paramsStr);
        $rspArray = json_decode($rsp, true);
        if($this->validSign($rspArray, $this->appkey)){
            if("SUCCESS" == $rspArray["retcode"]){
                if (!$rspArray['payinfo']) {
                    \YZ\Core\Logger\Log::writeLog("tlpay-unitorder", "请求接口出错 ".$rspArray['retmsg'].$rspArray['errmsg'].' 状态码 '.$rspArray['trxstatus']);
                    throw new \Exception($rsp);
                }
                $result = [];
                if($payType == 'A01') { //支付宝扫码支付
                    $result['data'] = $rspArray['payinfo'];
                    $result['trade_type'] = 'REDIRECT';
                    return $result;
                }elseif($payType == 'W02') { //微信JS支付
                    $result['data'] = $rspArray['payinfo'];
                    $result['trade_type'] = 'WXJS';
                    return $result;
                }else{
                    throw new \Exception("暂时不支持此支付方式 ".$payType);
                }
            }else{
                \YZ\Core\Logger\Log::writeLog("tlpay-unitorder", "请求接口出错 ".$rspArray['retmsg'].$rspArray['errmsg'].' 状态码 '.$rspArray['trxstatus']);
                throw new \Exception("请求接口出错 ".$rspArray['retmsg'].$rspArray['errmsg']);
            }
        }else{
            throw new \Exception("签名验证失败");
        }
    }

    /**
     * 处理微信支付回调
     * @param string $orderId 订单号
     * @return bool|PaymentResult
     */
    public function checkReturn($orderId = '')
    {
        try {
            if(!$orderId) {
                $orderId = Request::get('orderid');
                if(!$orderId) $orderId = Request::route('orderid');
            }
            $params = $this->getCommonOptions();
            $params["version"] = "11";
            $params["reqsn"] = $orderId;
            $params["randomstr"] = randString(10);
            $params["sign"] = static::SignArray($params,$this->appkey);//签名
            $paramsStr = static::ToUrlParams($params);
            $url = $this->query_api;
            $rsp = $this->request($url, $paramsStr);
            $rspArray = json_decode($rsp, true);
            \YZ\Core\Logger\Log::writeLog("tlpay-return", "data = " . var_export($rspArray, true));
            if($this->validSign($rspArray, $this->appkey)){
                if("SUCCESS" == $rspArray["retcode"] && strval($rspArray['trxstatus']) === '0000'){
                    $success = true;
                    $amount = $rspArray['trxamt']; //转换为分
                    $tradeno = $rspArray['trxid'];
                    $orderid = $this->parseOrderId($rspArray['reqsn']);
                    $paytype = \YZ\Core\Constants::PayType_TongLian;
                    return new PaymentResult($success, $tradeno, $orderid, $amount, $paytype, null, $rspArray);
                }else{
                    \YZ\Core\Logger\Log::writeLog("tlpay-return", "error: " . "查询失败 ".$rspArray['retmsg'].$rspArray['errmsg'].' 交易状态码 '.$rspArray['trxstatus']);
                }
            } else {
                \YZ\Core\Logger\Log::writeLog("tlpay-return", "error: " . "验证签名失败 ".$rspArray['retmsg'].$rspArray['errmsg']);
            }
        } catch (\Exception $ex) {
            \YZ\Core\Logger\Log::writeLog("tlpay-return", "error: " . $ex->getMessage());
        }
        return false;
    }

    /**
     * 处理支付异步通知
     * @return bool|null
     */
    public function checkNotify()
    {
        try {
            $params = Request::all();
            \YZ\Core\Logger\Log::writeLog("tlpay-notify", "data = " . var_export($params, true));
            if($this->validSign($params, $this->appkey)){
                if(strval($params['trxstatus']) === '0000'){
                    $success = true;
                    $amount = $params['trxamt'];
                    $tradeno = $params['trxid'];
                    $orderid = $this->parseOrderId($params['cusorderid']);
                    $paytype = \YZ\Core\Constants::PayType_TongLian;
                    $payresult = new PaymentResult($success, $tradeno, $orderid, $amount, $paytype, null, $params);
                    \YZ\Core\Logger\Log::writeLog("tlpay-notify", "notify success: data = " . var_export($params, true));
                    return $payresult;
                } else {
                    throw new \Exception('tlpay pay notify error');
                }
            }else{
                throw new \Exception('tlpay pay notify error: sign error');
            }
        } catch (\Exception $ex) {
            \YZ\Core\Logger\Log::writeLog("tlpay-notify", "error: " . $ex->getMessage());
            return false;
        }
        return false;
    }

    /**
     * 微信支付退款
     * @param string $transactionId 原支付的交易号
     * @param string $orderId 原订单号
     * @param string $refundNumber 退款单号，由商户生成
     * @param int $totalFee 原支付的金额
     * @param int $refundFee 要退款的金额(单位分)
     * @param array $config 其它配置，如 ['refund_desc' => '商品已售完']
     * @return RefundResult
     */
    public function refund(string $transactionId,string $orderId, string $refundNumber, int $totalFee, int $refundFee, array $config = [])
    {
        try {
            $params = $this->getCommonOptions();
            $params["version"] = "11";
            $params["reqsn"] = $refundNumber;
            $params["trxamt"] = $refundFee;
            $params["oldtrxid"] = $transactionId;
            $params["remark"] = $config['refund_desc'];
            $params["randomstr"] = randString(10);
            $params["sign"] = static::SignArray($params,$this->appkey);//签名
            $paramsStr = static::ToUrlParams($params);
            $url = $this->refund_api;
            $rsp = $this->request($url, $paramsStr);
            $rspArray = json_decode($rsp, true);
            \YZ\Core\Logger\Log::writeLog("tlpay-refund", "data = " . var_export($rspArray, true));
            if($this->validSign($rspArray, $this->appkey)){
                if("SUCCESS" == $rspArray["retcode"] && strval($rspArray['trxstatus']) === '0000'){
                    $result = new RefundResult();
                    $result->orderid = $this->parseOrderId($orderId);
                    $result->amount = $refundFee;
                    $result->tradeno = $transactionId;
                    $result->paytype = Constants::PayType_TongLian;
                    $result->apidata = $rspArray;
                    $result->refundNo = $refundNumber;
                    $result->refundTradeNo = $rspArray['trxid'];
                    $result->success = true;
                    return $result;
                }else{
                    throw new \Exception("请求接口返回出错:".$rspArray['retmsg'].$rspArray['errmsg']);
                }
            } else {
                throw new \Exception("验证签名失败:".$rspArray['retmsg'].$rspArray['errmsg']);
            }
        } catch (\Exception $ex) {
            \YZ\Core\Logger\Log::writeLog("tlpay-refund", "error: " . $ex->getMessage());
            throw new \Exception($ex->getMessage());
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

    //发送请求操作仅供参考,不为最佳实践
    private function request($url,$params){
        $ch = curl_init();
        $this_header = array("content-type: application/x-www-form-urlencoded;charset=UTF-8");
        curl_setopt($ch,CURLOPT_HTTPHEADER,$this_header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);//如果不加验证,就设false,商户自行处理
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        $output = curl_exec($ch);
        curl_close($ch);
        return  $output;
    }
}