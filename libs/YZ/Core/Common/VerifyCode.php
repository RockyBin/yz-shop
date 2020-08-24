<?php

namespace YZ\Core\Common;

use Illuminate\Support\Facades\Session;
use YZ\Core\Constants;
use YZ\Core\Sms\SmsApi;

class VerifyCode
{
    /**
     * 获取图形验证码
     * @return \Mews\Captcha\ImageManager
     */
    public static function getImgCode()
    {
        return \Captcha::create();
    }

    /**
     * 检测图形验证码是否正确
     * @param $value 验证码的值
     * @param string $key 验证码的表单名
     * @return bool
     */
    public static function checkImgCode($value, $key = 'captcha')
    {
        $rules = [$key => 'required|captcha'];
        $validator = \Validator::make([$key => $value], $rules);
        return !$validator->fails();
    }

    /**
     * 获取手机验证码
     * @param $mobile 手机号
     * @param int $activeTime 有效时间，单位：秒
     * @return array
     * @throws \Exception
     */
    public static function getSmsCode($mobile, $activeTime = 300)
    {
        if (Session::has(Constants::SessionKey_SmsCode_LastTime)) {
            $lastTime = intval(session(Constants::SessionKey_SmsCode_LastTime));
            if ($lastTime + 60 > time()) {
                $second = $lastTime + 60 - time();
                $msg = str_replace('{second}', $second, trans('base-front.common.sms_code_time_error'));
                return makeServiceResultFail($msg, ['second' => $second]);
            }
        }
        $code = static::genCode();
        $content = "您的验证码是 " . $code;
        if (SmsApi::sendSmsBySite($content, $mobile)) {
            Session::put(Constants::SessionKey_SmsCode_LastTime, time());
            Session::put(Constants::SessionKey_SmsCode_ExpireTime, time() + intval($activeTime));
            Session::put('SmsCode.Code.' . $mobile, $code);
            return makeServiceResultSuccess('ok');
        } else {
            return makeServiceResultFail('send error');
        }
    }

    /**
     * 检测手机验证码是否正确
     * @param $mobile 手机号
     * @param $code 验证码的值
     * @return bool
     */
    public static function checkSmsCode($mobile, $code)
    {
        $key = 'SmsCode.Code.' . $mobile;
        if (!Session::has($key) || !Session::has(Constants::SessionKey_SmsCode_ExpireTime)) {
            return makeServiceResultFail(trans('base-front.common.verify_code_fail'));
        }
        // 验证码有效期
        $expireTime = intval(Session::get(Constants::SessionKey_SmsCode_ExpireTime));
        if (time() > $expireTime) {
            return makeServiceResultFail(trans('base-front.common.sms_code_time_over'));
        }
        // 验证码是否正确
        if (Session::get($key) === $code) {
            Session::remove($key);
            return makeServiceResultSuccess('ok');
        } else {
            return makeServiceResultFail(trans('base-front.common.verify_code_fail'));
        }
    }

    /**
     * 清理掉验证码的时间
     * 以便在验证完旧手机再验证新手机时不会被时间拦截
     */
    public static function clearSmsCodeLastTime()
    {
        Session::remove(Constants::SessionKey_SmsCode_LastTime);
    }

    /**
     * 生成验证码字符串
     * @return string
     */
    private static function genCode()
    {
        $str = "";
        while (strlen($str) < 4) {
            $str .= mt_rand(0, 9);
            usleep(5000);
        }
        return $str;
    }
}