<?php

namespace YZ\Core\Payment;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Jenssegers\Agent\Agent;
use YZ\Core\Common\ServerInfo;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\OnlinepayLogModel;
use YZ\Core\Locker\Locker;
use \YZ\Core\Finance\Finance;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use YZ\Core\Weixin\WxWork;
use YZ\Core\Weixin\WxApp;

class Payment
{
    /**
     * 发起支付请求
     * @param $orderId 订单号
     * @param $memberId 会员ID
     * @param $amount 金额（单位：分）
     * @param $callback 支付成功入帐后的回调事件处理程序
     * @param $payType 支付方式（参考 Constants::PayType_XXX）
     * @param $isOrder 是否订单交费时的充值（与普通余额充值进行区分）
     * @param int $orderType 订单类型，1 = 零售订单，2 = 代理进货单，6 = 代理加盟费，7 = 经销商加盟费
     * @param array $extParams 扩展的参数，对于某些支付接口如果需要额外的支付参数，可以在这里指定
     * @return mixed 返回相应支付方式需要的数据，
     * 比如支付宝时，是返回自动跳转的表单，
     * 微信公众号支付时，是返回相应的 jssdk 需要的参数，
     * 微信扫码支付时，返回的是支付所用的二维码
     * @throws \Exception
     */
    public static function doPay($orderId, $memberId, $amount, $callback, $payType, $isOrder = 0, $orderType = 1, $extParams = [])
    {
        //记录付款的渠道
        $payChannel = getCurrentTerminal();
        Cache::set('pay_channel_' . $orderId, $payChannel, 600);
        Cache::set('pay_isorder_' . $orderId, $isOrder, 600);
        Cache::set('pay_ordertype_' . $orderId, $orderType, 600);
        //执行付款
        switch ($payType) {
            case Constants::PayType_Alipay:
                return self::doAlipay($orderId, $memberId, $amount, $callback);
                break;
            case Constants::PayType_Weixin:
                return self::doWeixinPay($orderId, $memberId, $amount, $callback);
                break;
            case Constants::PayType_TongLian:
                return self::doTLPay($orderId, $memberId, $amount, $callback, $extParams);
                break;
            case Constants::PayType_Balance:
                self::doBalancePay($orderId);
                break;
            default:
                throw new \Exception(trans('shop-front.shop.pay_type_error'));
        }
    }

    /**
     * 订单余额支付
     * @param $orderId
     */
    public static function doBalancePay($orderId)
    {
        $order = ShopOrderFactory::createOrderByOrderId($orderId);
        $payInfo = [
            'pay_type' => Constants::PayType_Balance,
            'terminal_type' => getCurrentTerminal(),
            'tradeno' => 'PAYORDER_' . $orderId
        ];
        $order->pay($payInfo);
    }

    /**
     * 获取支付宝支付的实例
     * @return Alipay
     */
    public static function getAlipayInstance()
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getPayConfig();

        $redirectUrl = "http://" . ServerInfo::get('HTTP_HOST') . "/core/member/payment/alipayreturn";
        $notifyUrl = "http://" . ServerInfo::get('HTTP_HOST') . "/core/member/payment/alipaynotify";
        $alipay = new \YZ\Core\Payment\Alipay($config->alipay_appid, $config->alipay_public_key, $config->alipay_private_key, $redirectUrl, $notifyUrl, $config->alipay_use_sandbox);
        return $alipay;
    }

    /**
     * 执行支付宝支付，会自动检测浏览器类型自动调用PC网站支付或手机网站支付
     * @param $orderId 订单ID
     * @param $memberId 会员ID
     * @param $amount 金额，单位:分
     * @param $callback
     * @return string|\提交表单HTML文本
     */
    private static function doAlipay($orderId, $memberId, $amount, $callback)
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getModel();
        $alipay = self::getAlipayInstance();
        $waptype = 2;
        $agent = new Agent();
        //$iswap = ($agent->isMobile() || $agent->isTablet()) && ($waptype & $config->alipay_types) == 2;
        $iswap = $agent->isMobile() || $agent->isTablet();
        $res = $alipay->doPay($orderId, $memberId, $amount, $iswap, $callback);
        return $res;
    }

    /**
     * 处理支付宝支付回调
     * @return mixed
     */
    public static function doAlipayReturn()
    {
        $alipay = self::getAlipayInstance();
        $res = $alipay->checkReturn();
        $memberid = Request::route('memberid');
        $callback = base64_decode(Request::route('callback'));
        $callback = Cache::get($callback);
        return self::addFinanceByPaymentResult($res, $memberid, $callback);
    }

    /**
     * 处理支付宝支付结果异步通知
     * @return mixed
     */
    public static function doAlipayNotify()
    {
        $alipay = self::getAlipayInstance();
        $res = $alipay->checkNotify();
        $memberid = Request::route('memberid');
        $callback = base64_decode(Request::route('callback'));
        $callback = Cache::get($callback);
        self::addFinanceByPaymentResult($res, $memberid, $callback);
        //打印 success，应答支付宝。必须保证本界面无错误。只打印了 success，否则支付宝将重复请求回调地址。
        return 'success';
    }

    /**
     * 支付宝退款
     * @param string $transactionId 原支付的交易号
     * @param string $refundNumber 退款单号，由商户生成
     * @param int $totalFee 原支付的金额
     * @param int $refundFee 要退款的金额
     * @param array $config 其它配置，如 ['refund_desc' => '商品已售完']
     * @return RefundResult
     * @throws \Exception
     */
    public static function doAlipayRefund(string $transactionId, string $refundNumber, int $totalFee, int $refundFee, array $config = [])
    {
        $alipay = self::getAlipayInstance();
        $res = $alipay->refund($transactionId, $refundNumber, $totalFee, $refundFee, $config);
        self::saveRefundResultLog($res, 0);
        return $res;
    }

    /**
     * 支付宝转帐给用户
     * @param $alipayUserId 用户的支付宝ID
     * @param $money 金额
     * @param $about 备注
     * @return RefundResult
     * @throws \Exception
     */
    public static function doAlipayToUser($meberId, $alipayUserId, $money, $about)
    {
        $alipay = self::getAlipayInstance();
        $res = $alipay->payToUser($alipayUserId, $money, $about);
        self::savePaymentResultLog($res, $meberId);
        return $res;
    }

    /**
     * 支付宝转帐给用户(手机邮箱等)
     * @param $alipayUserId 用户的支付宝ID
     * @param $money 金额
     * @param $about 备注
     * @return RefundResult
     * @throws \Exception
     */
    public static function doAlipayToUserByAccount($meberId, $alipayAccount, $money, $about)
    {
        $alipay = self::getAlipayInstance();
        $res = $alipay->payToUserByAccount($alipayAccount, $money, $about);
        self::savePaymentResultLog($res, $meberId);
        return $res;
    }


    /**
     * 获取微信支付实例
	 * @param $appType app类型，0=公众号，1=企业微信，2=微信小程序
     * @return WeixinPay
     */
    private static function getWeixinPayInstance($appType = 0)
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getPayConfig();
        $wxconfig = $site->getOfficialAccount()->getConfig()->getModel();
        $redirectUrl = "http://" . ServerInfo::get('HTTP_HOST') . "/core/member/payment/weixinpayreturn";
        $notifyUrl = "http://" . ServerInfo::get('HTTP_HOST') . "/core/member/payment/weixinpaynotify";
        $certpath = Site::getSiteComdataDir($site->getSiteId(), true) . $config->wxpay_cert;
        $keypath = Site::getSiteComdataDir($site->getSiteId(), true) . $config->wxpay_cert_key;
        $is_service_mode = $config->wxpay_service_mode;
        $appid = $wxconfig->appid;
        $sAppType = 'no'; //注意这里不能为空，否则回调URL会以/结束，这时坑爹的apache会自动301一下，导致检测回调不成功
        if ($appType === 1) {
            $wxWork = new WxWork();
            $appid = $wxWork->getConfig()['corp_id'];
            $sAppType = 'wxwork';
        }elseif ($appType === 2) {
            $wxApp = new WxApp();
            $appid = $wxApp->getConfig()['app_id'];
			$sAppType = 'wxapp';
        }
        $wxpay = new \YZ\Core\Payment\WeixinPay($config->wxpay_mchid, $config->wxpay_key, $appid, $redirectUrl, $notifyUrl, $certpath, $keypath, $is_service_mode, $sAppType);
        return $wxpay;
    }

    /**
     * 执行微信支付，会根据浏览器类型自动调用 PC 扫码支付，公众号内JSSDK支付 或 手机浏览器支付
     * @param $orderId
     * @param $memberId
     * @param $amount
     * @param $callback
     * @return array|\EasyWeChat\Kernel\Support\Collection|mixed|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    private static function doWeixinPay($orderId, $memberId, $amount, $callback)
    {
        $appType = 0;
        if(getCurrentTerminal() == Constants::TerminalType_WxWork){
            $appType = 1;
        }
        if(getCurrentTerminal() == Constants::TerminalType_WxApp){
            $appType = 2;
        }
        $weixinpay = self::getWeixinPayInstance($appType);
        $agent = new Agent();
        $iswx = $agent->match('MicroMessenger');
        $iswap = ($agent->isMobile() || $agent->isTablet());
        if($appType == 1) {
            $wxWork = new WxWork();
            $openid = $wxWork->getUserOpenId(Session::get('WxWorkAccountId'));
        }elseif($appType == 2){
            $openid = Session::get('WxAppAccountId');
        }else{
            $openid = Session::get('WxOficialAccountOpenId');
        }
        if ($iswx) {
            $res = $weixinpay->doPayWithJSSDK($openid, $orderId, $memberId, $amount, $callback);
            if($appType == 2){
                $res['app_type'] = $appType;
                $res['jump_wxapp_pay'] = 1;
                $res['order_id'] = $orderId;
                $res['money'] = moneyCent2Yuan($amount);
            }
        }
        elseif ($iswap) $res = $weixinpay->doPayWithH5($orderId, $memberId, $amount, $callback);
        else $res = $weixinpay->doPayWithQrCode($orderId, $memberId, $amount, $callback);
        return $res;
    }

    /**
     * 处理微信支付回调
     * @return mixed
     */
    public static function doWeixinPayReturn()
    {
		$appType = 0;
        if(stripos(ServerInfo::get('REQUEST_URI'),'/wxwork') !== false) $appType = 1;
		if(stripos(ServerInfo::get('REQUEST_URI'),'/wxapp') !== false) $appType = 2;
        $weixinpay = self::getWeixinPayInstance($appType);
        //$orderid = Request::route('orderid');
        $transactionId = Request::get('transaction_id');
        $res = $weixinpay->checkReturn($transactionId);
        $memberid = Request::route('memberid');
        $callback = base64_decode(Request::route('callback'));
        if ($res instanceof PaymentResult) return self::addFinanceByPaymentResult($res, $memberid, $callback);
        else return $res;
    }

    /**
     * 处理微信支付结果通知
     * @return mixed
     */
    public static function doWeixinPayNotify()
    {
		$appType = 0;
        if(stripos(ServerInfo::get('REQUEST_URI'),'/wxwork') !== false) $appType = 1;
		if(stripos(ServerInfo::get('REQUEST_URI'),'/wxapp') !== false) $appType = 2;
        $weixinpay = self::getWeixinPayInstance($appType);
        $res = $weixinpay->checkNotify();
        $memberid = Request::route('memberid');
        $callback = base64_decode(Request::route('callback'));
        $result = self::addFinanceByPaymentResult($res, $memberid, $callback);
        $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        return response($str, 200)->header("Content-type", "text/xml");
    }

    /**
     * 微信支付退款
     * @param string $transactionId 原支付的交易号
     * @param string $refundNumber 退款单号，由商户生成
     * @param int $totalFee 原支付的金额
     * @param int $refundFee 要退款的金额
     * @param array $config 其它配置，如 ['refund_desc' => '商品已售完']
     * @return RefundResult
     * @throws \Exception
     */
    public static function doWeixinPayRefund(string $transactionId, string $refundNumber, int $totalFee, int $refundFee, array $config = [])
    {
        //这里暂时未处理企业微信的退款问题，如要处理，需要先从 tbl_onlinepay_log 根据 $transactionId 找出当时的支付记录，再分析出当时是否是使用企业微信支付
        $weixinpay = self::getWeixinPayInstance();
        $res = $weixinpay->refund($transactionId, $refundNumber, $totalFee, $refundFee, $config);
        self::saveRefundResultLog($res, 0);
        return $res;
    }

    /**
     * 微信企业付款到用户
     * @param $openid OPENID
     * @param $money 金额
     * @param $about 备注
     * @return RefundResult
     * @throws \Exception
     */
    public static function doWeixinPayToUser($meberId, $openid, $money, $about)
    {
        $weixinpay = self::getWeixinPayInstance();
        $res = $weixinpay->payToUser($openid, $money, $about);
        self::savePaymentResultLog($res, $meberId);
        return $res;
    }

    /**
     * @return TLPay
     */
    public static function getTLPayInstance()
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getPayConfig();
        $redirectUrl = "http://" . ServerInfo::get('HTTP_HOST') . "/core/member/payment/tlpayreturn";
        $notifyUrl = "http://" . ServerInfo::get('HTTP_HOST') . "/core/member/payment/tlpaynotify";
        $tlpay = new \YZ\Core\Payment\TLPay($config->tlpay_mchid, $config->tlpay_key, $config->tlpay_appid, $redirectUrl, $notifyUrl);
        return $tlpay;
    }

    /**
     * 执行通联支付，会根据浏览器类型自动调用 PC 扫码支付，公众号内支付 或 手机浏览器支付
     * @param $orderId
     * @param $memberId
     * @param $amount
     * @param $callback
     * @param array $extParams 扩展的参数，比如在这里指定统一下单接口使用哪种支付方式等
     * @return array|\EasyWeChat\Kernel\Support\Collection|mixed|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    private static function doTLPay($orderId, $memberId, $amount, $callback, $extParams = [])
    {
        if(getCurrentTerminal() == Constants::TerminalType_WxWork){
            throw new \Exception("通联支付暂不支持企业微信");
        }
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getPayConfig();
        $tlpay = self::getTLPayInstance();
        $agent = new Agent();
        $iswx = $agent->match('MicroMessenger');
        $iswap = ($agent->isMobile() || $agent->isTablet());
        $openid = Session::get('WxOficialAccountOpenId');
        if(getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxApp) {
            $openid = Session::get('WxAppAccountId');
        }
        if ($iswx && $openid && $config->tlpay_wxtype != 'h5') $res = $tlpay->doPayWithUnion($orderId, $memberId, $amount, $callback, 'W02', $openid);
        elseif ($iswx) $res = $tlpay->doPayWithH5($orderId, $memberId, $amount, $callback);
        elseif ($iswap) $res = $tlpay->doPayWithUnion($orderId, $memberId, $amount, $callback, $extParams['pay_type'] ? $extParams['pay_type'] : 'A01'); //手机端暂时只用支付宝扫码
        else $res = $tlpay->doPayWithUnion($orderId, $memberId, $amount, $callback,$extParams['pay_type'] ? $extParams['pay_type'] : 'A01'); //PC端暂时先走用支付宝扫码
        if(getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxApp) {
            $res['app_type'] = 2;
            $res['jump_wxapp_pay'] = 1;
            $res['order_id'] = $orderId;
            $res['json'] = $res['data']; //为保证小程序端得到的JSON格式统一，改写此字段数据后返回
            $res['money'] = moneyCent2Yuan($amount);
            unset($res['data']);
        }
        return $res;
    }

    /**
     * 处理通联支付回调
     * @return mixed
     */
    public static function doTLPayReturn()
    {
        $tlpay = self::getTLPayInstance();
        $orderid = Request::get('orderid');
        if(!$orderid) $orderid = Request::route('orderid');
        $res = $tlpay->checkReturn($orderid);
        $memberid = Request::route('memberid');
        $callback = base64_decode(Request::route('callback'));
        if ($res instanceof PaymentResult) return self::addFinanceByPaymentResult($res, $memberid, $callback);
        else return $res;
    }

    /**
     * 处理通联支付结果通知
     * @return mixed
     */
    public static function doTLPayNotify()
    {
        $tlpay = self::getTLPayInstance();
        $res = $tlpay->checkNotify();
        $memberid = Request::route('memberid');
        $callback = base64_decode(Request::route('callback'));
        if ($res instanceof PaymentResult){
            $result = self::addFinanceByPaymentResult($res, $memberid, $callback);
            $str = 'success';
        }else{
            $str = 'error';
        }
        return response($str, 200)->header("Content-type", "text/plain");
    }

    /**
     * 通联支付退款
     * @param string $transactionId 原支付的交易号
     * @param string $orderId 原订单号
     * @param string $refundNumber 退款单号，由商户生成
     * @param int $totalFee 原支付的金额
     * @param int $refundFee 要退款的金额
     * @param array $config 其它配置，如 ['refund_desc' => '商品已售完']
     * @return RefundResult
     * @throws \Exception
     */
    public static function doTLPayRefund(string $transactionId, string $orderId, string $refundNumber, int $totalFee, int $refundFee, array $config = [])
    {
        $tlpay = self::getTLPayInstance();
        $res = $tlpay->refund($transactionId, $orderId, $refundNumber, $totalFee, $refundFee, $config);
        if ($res instanceof RefundResult) self::saveRefundResultLog($res, 0);
        return $res;
    }

    /**
     * 生成线下支付的支付凭证收款信息的统一格式
     * @param $payType 线下支付类型
     * @param $account 收款帐号 如果是支付宝、微信等二维码收款，请传入收款二维码的图片地址，如果是银行收款，传入银行帐号；如果是支付宝转帐，传入支付宝帐号
     * @param $bank 如果是银行收款，传入银行名称，其它先置空
     * @param $accountName 如果是银行收款，传入银行户名，其它先置空
     * @param $bankBranch 如果是银行收款，传入银行支行名称
     */
    public static function makeOffLinePaymentReceiptInfo($payType, $account, $bank, $accountName,$bankBranch = '')
    {
        if($payType == Constants::PayType_Bank){
            if(!$bank) throw new \Exception('银行不能为空');
            if(!$accountName) throw new \Exception('开户名为空');
        }elseif($payType == Constants::PayType_WeixinQrcode){
            $bank = '线下结算-微信';
        }elseif($payType == Constants::PayType_AlipayAccount){
            $bank = '线下结算-支付宝';
        }elseif($payType == Constants::PayType_AlipayQrcode){
            $bank = '线下结算-支付宝';
        }
        return [
            'pay_type' => $payType,
            'account' => $account,
            'bank' => $bank,
            'bank_branch' => $bankBranch,
            'account_name' => $accountName
        ];
    }

    /**
     * 支付成功后添加财务记录并执行入帐后的回调事件
     * @param PaymentResult $result 封装的支付结果对象
     * @param $memberId 会员ID
     * @param $callback 入帐后的回调事件
     * @return mixed
     * @throws \Exception
     */
    private static function addFinanceByPaymentResult(PaymentResult $result, $memberId, $callback)
    {
        $site = Site::getCurrentSite();
        $channel = Cache::get('pay_channel_' . $result->orderid);
        $isOrder = Cache::get('pay_isorder_' . $result->orderid);
        $orderType = Cache::get('pay_ordertype_' . $result->orderid);
        // 如果是跟订单有关系，代表订单直接支付，否则是充值
        $financeType = $isOrder ? Constants::FinanceType_Transfer : Constants::FinanceType_Normal;
        $inType = $isOrder ? Constants::FinanceInType_Trade : Constants::FinanceInType_Recharge;
        $about = '向公司充值-在线充值';
        if ($isOrder) $about = '在线支付，订单号：' . $result->orderid;
        if ($isOrder && $orderType == Constants::FinanceOrderType_CloudStock_Purchase) $about = '支付云仓进货单，进货单号：' . $result->orderid;
        if ($isOrder && $orderType == Constants::FinanceOrderType_CloudStock_TakeDelivery) $about = '支付云仓提货运费，提货单号：' . $result->orderid;
        // 代理加盟费
        if ($orderType == 6) {
            $financeType = Constants::FinanceType_AgentInitial;
            $inType = Constants::FinanceInType_AgentInitial;
            $about = "支付代理加盟费";
        }
        // 经销商加盟费
        if ($orderType == 7) {
            $financeType = Constants::FinanceType_DealerInitial;
            $inType = Constants::FinanceInType_DealerInitial;
            $about = "支付经销商加盟费";
        }
        $finInfo = [
            'site_id' => $site->getSiteId(),
            'member_id' => $memberId,
            'type' => $financeType,
            'pay_type' => $result->paytype,
            'tradeno' => $result->tradeno,
            'order_id' => $result->orderid,
            'order_type' => $isOrder ? $orderType : 0,
            'is_real' => Constants::FinanceIsReal_Yes,
            'in_type' => $inType,
            'operator' => '',
            'terminal_type' => $channel,
            'money' => $result->amount,
            'created_at' => date('Y-m-d H:i:s'),
            'about' => $about,
            'status' => Constants::FinanceStatus_Active,
            'active_at' => date('Y-m-d H:i:s'),
        ];
        $locker = new Locker($result->orderid . $result->tradeno, 20);
        if ($locker->lock()) {
            $finRecord = FinanceModel::query()
                ->where('tradeno', $result->tradeno)
                ->first();
            if (!$finRecord) {
                // 记录支付接口日志
                self::savePaymentResultLog($result, $memberId);
                // 保存财务记录
                $fin = new Finance();
                $fin->add($finInfo, false);
            } else {
                $fin = new Finance($finRecord);
            }
            $fin->addOnAddEvent($callback);
            $locker->unlock();
            return $fin->fireAddEvent();
        }
        // return Event::fireEvent('onAddFinance_'.static::class,$callback,$finInfo);
    }

    private static function savePaymentResultLog(PaymentResult $result, $meberId)
    {
        $site = Site::getCurrentSite();
        $log = new OnlinepayLogModel();
        $log->site_id = $site->getSiteId();
        $log->member_id = $meberId;
        $log->order_id = $result->orderid;
        $log->success = $result->success ? 1 : 0;
        $log->paytype = $result->paytype;
        $log->tradeno = $result->tradeno;
        $log->amount = $result->amount;
        $log->apidata = json_encode($result->apidata);
        $log->save();
    }

    private static function saveRefundResultLog(RefundResult $result, $meberId)
    {
        $site = Site::getCurrentSite();
        $log = new OnlinepayLogModel();
        $log->site_id = $site->getSiteId();
        $log->member_id = $meberId;
        $log->order_id = $result->orderid;
        $log->success = $result->success ? 1 : 0;
        $log->paytype = $result->paytype;
        $log->tradeno = $result->tradeno;
        $log->amount = $result->amount;
        $log->apidata = json_encode($result->apidata);
        $log->save();
    }
}