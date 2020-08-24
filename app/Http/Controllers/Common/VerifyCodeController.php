<?php

namespace App\Http\Controllers\Common;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Request;
use YZ\Core\Common\VerifyCode;

class VerifyCodeController extends BaseController
{
    /**
     * 获取图形验证码
     * @return \Mews\Captcha\ImageManager
     */
    public function index()
    {
        return VerifyCode::getImgCode();
    }

    /**
     * 检测图形验证码是否正确
     * @return string
     */
    public function check()
    {
        if (!VerifyCode::checkImgCode(Request::get('captcha'))) {
            return '<p style="color: #ff0000;">Incorrect!</p>';
        } else {
            return '<p style="color: #00ff30;">Matched :)</p>';
        }
    }

    /**
     * 获取手机验证码
     * @return array
     */
    public function getSmsCode()
    {
        try {
            $mobile = trim(Request::get('mobile'));
            $ret = VerifyCode::getSmsCode($mobile);
            if ($ret['code'] == 200) return makeApiResponseSuccess('ok');
            else return makeServiceResult($ret['code'], $ret['msg'], $ret['data']);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 检测手机验证码是否正确
     * @return string
     */
    public function checkSmsCode()
    {
        $mobile = trim(Request::get('mobile'));
        $code = trim(Request::get('code'));
        $verifyCodeResult = VerifyCode::checkSmsCode($mobile, $code);
        return makeApiResponse($verifyCodeResult['code'], $verifyCodeResult['msg'], $verifyCodeResult['data']);
    }
}