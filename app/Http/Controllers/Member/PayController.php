<?php
namespace App\Http\Controllers\Member;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cache;
use YZ\Core\Site\Site;
use YZ\Core\Member\Auth;
use YZ\Core\Constants;

class PayController extends BaseController
{
    public function __construct()
    {
        
    }

    /**
     * 选择支付方式
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function doChoose(){
        $callback = Request::get('callback');
        $orderid = Request::get('orderid');
        $amount = Request::get('amount');
        $memberid = Request::get('memberid');

        //检测是否登录
        if(!Auth::hasLogin()){
            $redirectUrl = getHttpProtocol() . "://" . \Illuminate\Support\Facades\Request::getHost() . "/core/member/payment/choose";
            Auth::wxLogin($redirectUrl);
//            return Auth::goLogin();
        }

        return view('Member/Payment/choose',['callback' => $callback,'orderid' => $orderid,'amount' => $amount,'memberid' => $memberid]);
    }

    /**
     * 执行支付
     * @return array
     */
    public function doPay(){
        $callback = Request::get('callback');
        $orderid = Request::get('orderid');
        $amount = Request::get('amount');
        $memberid = Request::get('memberid');
        $paytype = Request::get('pay_type');

        if(!$orderid) $orderid = date("YmdHis"); //测试用，后面可以删除
        if(!$callback) $callback = "\\".static::class."@testCallBack"; //测试用，后面可以删除

        try {
            $res = \YZ\Core\Payment\Payment::doPay($orderid, $memberid, $amount, $callback, $paytype);
            return ['success' => true,'orderid' => $orderid,'callback' => $callback,'memberid' => $memberid,'result' => $res];
        }catch(\Exception $ex){
            return ['success' => false,'msg' => $ex->getMessage()];
        }
    }

    /**
     * 处理支付宝回调
     * @return mixed
     */
    public function doAlipayReturn(){
        return \YZ\Core\Payment\Payment::doAlipayReturn();
    }

    /**
     * 处理支付宝异步通知
     * @return mixed
     */
    public function doAlipayNotify(){
        $res = \YZ\Core\Payment\Payment::doAlipayNotify();
        return $res;
    }

    /**
     * 处理微信回调
     * @return mixed
     */
    public function doWeixinPayReturn(){
        $res = \YZ\Core\Payment\Payment::doWeixinPayReturn();
        return $res;
    }

    /**
     * 处理微信异步通知
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function doWeixinPayNotify(){
        try {
            $res = \YZ\Core\Payment\Payment::doWeixinPayNotify();
            $orderid = Request::route('orderid');
            Cache::set('weixin_notify_'.$orderid,'true',60);
            return $res;
        }catch (\Exception $e) {
            \YZ\Core\Logger\Log::writeLog("weixin_notify", "error = ".var_export($e->getMessage(),true));
        }
    }

    /**
     * 检测扫码支付的结果
     * @return array
     */
    public function doWeixinPayCheckScan(){
        $orderid = Request::get('orderid');
        $key = 'weixin_notify_'.$orderid;
        $flag = Cache::get($key);
        return ['success' => true,'flag' => $flag == 'true'];
    }

    /**
     * 处理通联支付回调
     * @return mixed
     */
    public function doTLPayReturn(){
        $res = \YZ\Core\Payment\Payment::doTLPayReturn();
        return $res;
    }

    /**
     * 处理通联支付异步通知
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function doTLPayNotify(){
        try {
            $res = \YZ\Core\Payment\Payment::doTLPayNotify();
            $orderid = Request::route('orderid');
            Cache::set('tlpay_notify_'.$orderid,'true',60);
            return $res;
        }catch (\Exception $e) {
            \YZ\Core\Logger\Log::writeLog("tlpay_notify", "error = ".var_export($e->getMessage(),true));
        }
    }

    /**
     * 没实际用途，测试用的，用来测试支付成功入帐后的回调事件是否能正常被调用
     * @param $info
     * @return mixed
     */
    public function testCallBack($info){
        \YZ\Core\Logger\Log::writeLog("pay-callback", ",$info = ".var_export($info,true));
        return $info;
    }

    /**
     * 获取微信支付的沙盘KEY
     * @return mixed
     */
    public function getSandboxInfo(){
        $site = Site::getCurrentSite();
		$config = $site->getConfig()->getPayConfig();
        $wxpay = new \YZ\Core\Payment\WeixinPay($config->wxpay_mchid,$config->wxpay_key,$wxAppId,'','');
        return $wxpay->getSandboxInfo();
    }
}