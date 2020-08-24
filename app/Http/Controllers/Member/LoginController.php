<?php

namespace App\Http\Controllers\Member;

use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use Closure;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cache;
use YZ\Core\Common\VerifyCode;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Fans;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Site\Site;
use YZ\Core\Member\Member;
use YZ\Core\Member\Auth;
use YZ\Core\Constants;
use YZ\Core\Weixin\WxApp;

class LoginController extends BaseController
{
    protected $urlBase = '/core/member/login'; // 当前地址相对路径
    protected $urlBaseRemote = ''; // 当前地址绝对路径
    protected $bindPageUrl = '/core/member/login/bind';
    protected $afterLogin = '/'; // 登录后去哪里
    protected $afterRegister = '/'; // 注册后去哪里

    /**
     * 初始化
     * LoginController constructor.
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function __construct()
    {
        $this->urlBaseRemote = getHttpProtocol() . "://" . Request::getHost() . $this->urlBase;
        if (Request::has('loginRedirect')) {
            $this->middleware(function ($request, Closure $next) {
                Session::put('loginRedirect', Request::get('loginRedirect'));
                return $next($request);
            });
        }
    }

    // 测试用
    public function index()
    {
        return view('Member/Login/index');
    }

    /**
     * 跳转微信公众号进行授权登录
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function wxLogin()
    {
        if (Auth::hasLogin()) {
            // return "has login,not need login again";
        }
        $redirectUrl = $this->urlBaseRemote . '/wxlogincallback';
        if (Request::get('scan') == '1') {
            $redirectUrl = $this->urlBaseRemote . '/wxscanlogincallback';
            $redirectUrl .= "/scan" . Request::get('scanid'); // 扫码登录时，通过检测scanid来确定用户是否有扫码的动作
        }
		if(Request::get('noreg')) $redirectUrl .= '?noreg=1';
        return Auth::wxLogin($redirectUrl);
    }

    /**
     * 显示扫码登录的所用的二维码的相关信息，它的url地址实际上是指向了 wxLogin() 这个方法的地址
     * scanid 是用来检测用户是否已经用手机扫码的标识
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function wxScanLogin()
    {
        $scanid = md5(mt_rand());
        $url = $this->urlBaseRemote . '/wxlogin?scan=1&scanid=' . $scanid;
        return makeApiResponseSuccess('ok', [
            'url' => $url,
            'scanid' => 'scan' . $scanid
        ]);
    }

    /**
     * 微信用户用手机扫码登录后的处理过程，实际上是将用户的相关信息写入cache
     * @return string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function wxScanLoginCallback()
    {
        $scanid = Request::route('scanid');
        $userinfo = Auth::getWxUserInfo();
        Cache::set('AuthUserInfo_' . $scanid, $userinfo, 60);
        Cache::set($scanid, 'true', 60);
        return trans('base-front.weixin.scan_ok');
    }

    /**
     * 检测用户是否已经扫码
     * @return array
     */
    public function wxCheckHasScan()
    {
        $scanid = Request::get('scanid');
        return ['success' => Cache::get($scanid) ? true : false];
    }

    /**
     * 微信确认登录后，自己注册或绑定会员的过程
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     * @throws \Exception
     */
    public function wxLoginCallBack()
    {
        // 获取第三方信息
        $scanid = Request::get("scanid");
        if ($scanid) $userinfo = Cache::get("AuthUserInfo_" . $scanid); // 如果是PC扫码登录，用户信息要从cache里读
        else $userinfo = Auth::getWxUserInfo(); // 如果是在手机微信里登录，用户信息直接从微信里读
        // 获取会员信息
        $member = Auth::getMemberWxOficialAccount($userinfo->openid);
        // 后续操作
        return $this->memberAfterAuth($userinfo, $member, Constants::MemberAuthType_WxOficialAccount);
    }

    /**
     * 跳转企业微信进行授权登录
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function wxWorkLogin()
    {
        if (Auth::hasLogin()) {
            // return "has login,not need login again";
        }
        $redirectUrl = $this->urlBaseRemote . '/wxlogincallback/wxwork';
		if(Request::get('noreg')) $redirectUrl .= '?noreg=1';
        return Auth::wxWorkLogin($redirectUrl);
    }

    /**
     * 企业微信确认登录后，自己注册或绑定会员的过程
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     * @throws \Exception
     */
    public function wxWorkLoginCallBack()
    {
        // 获取第三方信息
        $scanid = Request::get("scanid");
        if ($scanid) $userinfo = Cache::get("AuthUserInfo_" . $scanid); // 如果是PC扫码登录，用户信息要从cache里读
        else $userinfo = Auth::getWxWorkUserInfo(); // 如果是在手机微信里登录，用户信息直接从微信里读
        // 获取会员信息
        $member = Auth::getMemberWxWorkAccount($userinfo->openid);
        // 后续操作
        return $this->memberAfterAuth($userinfo, $member, Constants::MemberAuthType_WxWork);
    }

    /**
     * 获取微信小程序的登录信息(session_key 和 openid)
     */
    public function wxAppGetSession(){
        try {
            //$code = Request::get("code");
            //$wxapp = new WxApp();
            //$session = $wxapp->login($code);
            $session = Auth::getWxAppUserInfo();
            $session->session_key = $session->extInfo['session_key'];
            $session->expires_in = $session->extInfo['expires_in'];
            return makeApiResponseSuccess("ok", $session);
        }catch(\Exception $ex){
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取微信小程序用户绑定的手机号
     * @return array
     */
    public function wxAppGetMobile(){
        try {
            $sessionKey = Request::get("sessionKey");
            if(!$sessionKey) $sessionKey = "000000";
            $iv = Request::get("iv");
            $encryptedData = Request::get("encryptedData");
            $wxapp = new WxApp();
            $data = $wxapp->getMobile($sessionKey,$iv,$encryptedData);
            return makeApiResponseSuccess("ok",$data);
        }catch(\Exception $ex){
            return makeApiResponseError($ex);
        }
    }

    /**
     * 跳转QQ进行授权登录
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function qqLogin()
    {
        if (Auth::hasLogin()) {
            //return "has login,not need login again";
        }
        $redirectUrl = getHttpProtocol() . "://" . Request::getHost() . '/core/member/login/qqcallback';
        return redirect(Auth::qqLogin($redirectUrl));
    }

    /**
     * QQ 登录的回调处理
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function qqLoginCallBack()
    {
        $redirectUrl = getHttpProtocol() . "://" . Request::getHost() . '/core/member/login/qqcallback';
        // 获取第三方信息
        $userinfo = Auth::getQqUserInfo($redirectUrl);
        // 获取会员信息
        $member = Auth::getMemberQqAccount($userinfo->openid);
        // 后续操作
        return $this->memberAfterAuth($userinfo, $member, Constants::MemberAuthType_QQ);
    }

    /**
     * 跳转支付宝网站进行授权登录
     * @return string
     */
    public function alipayLogin()
    {
        $redirectUrl = "http://" . ServerInfo::get('HTTP_HOST') . "/core/member/login/alipaycallback";
        return Auth::alipayLogin($redirectUrl);
    }

    /**
     * 支付宝 登录的回调处理
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     * @throws \Exception
     */
    public function alipayLoginCallback()
    {
        // 获取第三方信息
        $userinfo = Auth::alipayGetUerInfo(request('auth_code'));
        // 尝试登录
        $member = Auth::getMemberAlipayAccount($userinfo->openid);
        // 后续操作
        return $this->memberAfterAuth($userinfo, $member, Constants::MemberAuthType_Alipay);
    }

    /**
     * 将微信、支付宝等授权帐号与会员进行绑定
     * @return array|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function doBind()
    {
        if (Request::get('showUI') == "1") {
            return view('Member/Login/bind', ['type' => Request::get('type')]);
        }
        $checkSms = 1;
        $mobile = Request::get('mobile');
        $code = Request::get('code');
        $type = Request::get('type');
        if(!$type){
            if(Session::get('WxOficialAccountOpenId')) $type = Constants::MemberAuthType_WxOficialAccount;
            elseif(Session::get('WxWorkAccountId')) $type = Constants::MemberAuthType_WxWork;
            elseif(Session::get('WxAppAccountId')) $type = Constants::MemberAuthType_WxApp;
            elseif(Session::get('AlipayUserId')) $type = Constants::MemberAuthType_Alipay;
            elseif(Session::get('QqOpenId')) $type = Constants::MemberAuthType_QQ;
        }
        $encmobile = Request::get('encmobile'); //微信小程序获取手机号登录时，为安全考虑，要使用加密过的手机号，此时不需要验证短信
        if($encmobile) {
            $des = new \Ipower\Common\CryptDes();
            $mobile = $des->decrypt($encmobile);
            $type = Constants::MemberAuthType_WxApp;
            $code = "no-need";
            $checkSms = 0;
        }
        $sessionKey = Request::get('session_key'); //目前只能微信小程序登录时用到
        $redirect = Request::get('loginRedirect');
        if(!$redirect) $redirect = Session::get('loginRedirect');
        $jump = Request::get('jump');
        if (!$mobile || !$code){
            return makeApiResponseFail(trans("base-front.common.data_miss"));
        }
        if($checkSms) {
            $verifyCodeResult = VerifyCode::checkSmsCode($mobile, $code);
            if (intval($verifyCodeResult['code']) != 200) {
                return makeApiResponse($verifyCodeResult['code'], $verifyCodeResult['msg'], $verifyCodeResult['data']);
            }
        }
        $userinfo = Session::get('UserInfo');
        if(!$userinfo) $userinfo = Cache::get($sessionKey);
        if ($userinfo) {
            $openid = $userinfo->openid;
        }
        if (!$openid) {
            return makeApiResponseFail(trans("base-front.common.data_error"));
        }

        $member = new Member();
        $member->findByMobile($mobile);
        $userInfoArray = (array)$userinfo;
        if (!$member->checkExist()) {
            $oldMemberId = Auth::hasLogin();
            if($oldMemberId) {
                $member = new Member($oldMemberId);
                $member->edit(['mobile' => $mobile]);
            }else{
                // 如果用户不存在，新建一个
                $userInfoArray['mobile'] = $mobile;
                if ($encmobile) {
                    $userInfoArray['nickname'] = substr($mobile, 0, 3) . "****" . substr($mobile, -4);
                }
                $member = $this->memberRegister($userInfoArray, intval($type));
                // 上下级关系
                $this->memberInvite($member, Request::all());
            }
        }
        // 第三方信息绑定用户
        if ($type == Constants::MemberAuthType_WxOficialAccount) {
            $member->bindWxOficialAccount($openid, $userInfoArray);
            Auth::getMemberWxOficialAccount($openid);
        } else if ($type == Constants::MemberAuthType_Alipay) {
            $member->bindAlipayAccount($openid, $userInfoArray);
            Auth::getMemberAlipayAccount($openid);
        } else if ($type == Constants::MemberAuthType_QQ) {
            $member->bindQqAccount($openid, $userInfoArray);
            Auth::getMemberQqAccount($openid);
        } else if ($type == Constants::MemberAuthType_WxWork) {
            $member->bindWxWorkAccount($openid, $userInfoArray);
            Auth::getMemberWxWorkAccount($openid);
        } else if ($type == Constants::MemberAuthType_WxApp) {
            $member->bindWxAppAccount($openid, $userInfoArray);
            Auth::getMemberWxAppAccount($openid);
        } else {
            return makeApiResponseFail(trans("base-front.common.data_not_support"));
        }
        // 会员登录
        if ($this->memberLogin($member)) {
            if($jump) return redirect($redirect);
            return makeApiResponseSuccess('ok', [
                'redirect' => $redirect
            ]);
        } else {
            return makeApiResponseFail(trans("base-front.common.data_not_support"));
        }
    }

    /**
     * 绑定界面
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showBind()
    {
        return view('Member/Login/bind', ['type' => Request::get('type')]);
    }

    /**
     * 手机验证码登录（包含注册）
     * @return array
     */
    public function mobileCodeLogin()
    {
        try {
            $memberId = 0;

            // 手机验证码
            $mobile = trim(Request::get('mobile'));
            $code = trim(Request::get('code'));
            $verifyCodeResult = VerifyCode::checkSmsCode($mobile, $code);
            if (intval($verifyCodeResult['code']) != 200) {
                return makeApiResponse($verifyCodeResult['code'], $verifyCodeResult['msg'], $verifyCodeResult['data']);
            }
            // 查看是否有此手机号码的账号
            $member = new Member();
            $member->findByMobile($mobile);
            if (Request::get('register')) {
                // 会员已存在
                if ($member->checkExist()) {
                    return makeApiResponse(400, trans("base-front.member.member_exist"));
                }
                $inviteCode = trim(Request::get('invite_code'));
                if ($inviteCode) {
                    $member->find($inviteCode);
                    if (!$member->checkExist()) {
                        return makeApiResponse(400, trans("base-front.member.invite_code_error"));
                    }
                }
                $param = [
                    'mobile' => $mobile
                ];
                // 如果会员不存在且进行注册
                $member = $this->memberRegister($param, Constants::MemberAuthType_Manual);
                // 上下级关系
                $this->memberInvite($member, Request::all());
            }
            // 登录
            if ($member->checkExist()) {
                //检测用户是否生效
                if (!$member->isActive()) {
                    return makeApiResponse(511, trans("base-front.member.freeze_user"));
                }
                if ($this->memberLogin($member)) {
                    $memberId = $member->getModelId();
                }
            } else {
                return makeApiResponse(400, trans("base-front.member.not_exist"));
            }

            if ($memberId) {
                return makeApiResponseSuccess(trans("base-front.member.login_ok"), [
                    'member_id' => $memberId,
                    'redirect' => Session::get('loginRedirect')
                ]);
            } else {
                return makeApiResponse(510, trans("base-front.member.login_password_error"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 用户名（手机、邮箱）密码登录
     * @return array
     */
    public function passwordLogin()
    {
        try {
            $memberId = 0;

            $password = trim(Request::get('password'));
            $mobile = trim(Request::get('mobile'));

            // 检查数据完整性
            if (empty($password) || empty($mobile)) {
                return makeApiResponse(510, trans("base-front.common.data_error"));
            }

            // 验证账户
            $member = new Member();
            if ($mobile) {
                //检测用户手机号码是否正确
                if (!$member->findByMobile($mobile)) {
                    return makeApiResponse(511, trans("base-front.member.not_exist"));
                }
            }
            //检测用户密码是否正确
            if (!$this->passwordCheck($member, $password)) {
                return makeApiResponse(511, trans("base-front.member.login_password_error"));
            }
            //检测用户是否生效
            if (!$member->isActive()) {
                return makeApiResponse(511, trans("base-front.member.freeze_user"));
            }

            $this->memberLogin($member);
            $memberId = $member->getModelId();

            if ($memberId) {
                return makeApiResponseSuccess(trans("base-front.member.login_ok"), [
                    'redirect' => Session::get('loginRedirect'),
                    'member_id' => $memberId
                ]);
            } else {
                return makeApiResponse(511, trans("base-front.member.login_password_error"));
            }

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 注册完会员后，绑定上下级
     * @param $member
     * @param $inputParams
     */
    protected function memberInvite($member, $inputParams)
    {
        // 这里具体现实交给应用层
    }

    /**
     * 会员注册方法，在应用层覆盖
     * @param $userInfo
     * @param $regFrom
     * @return Member
     */
    protected function memberRegister($userInfo, $regFrom)
    {
        // 这里具体现实交给应用层
    }

    /**
     * 会员登录方法，在应用层覆盖
     * @param Member $member
     * @return bool
     * @throws \Exception
     */
    protected function memberLogin(Member $member)
    {
        if (!$member || !$member->isActive()) {
            return false;
        }
        $member->login();
        return true;
    }

    /**
     * 检查密码书否正确，需要覆写
     * @param $member
     * @param $password
     * @return bool
     */
    protected function passwordCheck($member, $password)
    {
        return false;
    }

    /**
     * 获取第三方信息后的操作
     * @param $userInfo
     * @param $member
     * @param $memberAuthType
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     * @throws \Exception
     */
    private function memberAfterAuth($userInfo, $member, $memberAuthType)
    {
        Session::put('UserInfo', $userInfo);
        if ($member) {
            // 如果用户存在，登录
            if ($this->memberLogin($member)) {
                if (Session::get('loginRedirect')) {
                    return redirect(Session::get('loginRedirect'));
                } else {
                    return redirect($this->afterLogin);
                }
            } else {
				if (Request::get('noreg') && Session::get('loginRedirect')) return redirect(Session::get('loginRedirect'));
                else return redirect('/');
            }
        } else {
			if (Request::get('noreg') && Session::get('loginRedirect')) return redirect(Session::get('loginRedirect'));
            //$site = Site::getCurrentSite();
            //$accountFlag = $site->getConfig()->getModel()->member_account_flag;
            $accountFlag = \YZ\Core\Constants::AccountFlag_Unified; // 先写死帐号要通过手机号打通(2020-04-16 改为授权时不再要求绑定手机)
            if ($accountFlag == \YZ\Core\Constants::AccountFlag_Unified && false) {
                // 如果需要绑定会员信息，跳转到绑定会员信息页面
                return redirect($this->bindPageUrl . '?type=' . $memberAuthType);
            } else {
                $userInfoArray = (array)$userInfo;
                // 会员注册
                $member = $this->memberRegister($userInfo, $memberAuthType);
                // 绑定第三方信息
                if ($memberAuthType == Constants::MemberAuthType_WxOficialAccount) {
                    $member->bindWxOficialAccount($userInfo->openid, $userInfoArray);
                } else if ($memberAuthType == Constants::MemberAuthType_QQ) {
                    $member->bindQqAccount($userInfo->openid, $userInfoArray);
                } else if ($memberAuthType == Constants::MemberAuthType_Alipay) {
                    $member->bindAlipayAccount($userInfo->openid, $userInfoArray);
                } else if ($memberAuthType == Constants::MemberAuthType_WxWork) {
                    $member->bindWxWorkAccount($userInfo->openid, $userInfoArray);
                } else if ($memberAuthType == Constants::MemberAuthType_WxApp) {
                    $member->bindWxAppAccount($userInfo->openid, $userInfoArray);
                }
                // 上下级关系
                $this->memberInvite($member, Request::all());
                // 会员登录
                if ($this->memberLogin($member)) {
                    if (Session::get('loginRedirect')) {
                        return redirect(Session::get('loginRedirect'));
                    } else {
                        return redirect($this->afterRegister);
                    }
                } else {
                    return redirect('/');
                }
            }
        }
    }

    /**
     * 获取推荐会员ID
     * @return int
     */
    protected function getMemberInvite()
    {
        $inviteCode = 0;
        if (!$inviteCode) $inviteCode = intval(Session::get('invite')); //其次从Session里取
        if (!$inviteCode) $inviteCode = intval(Request::cookie('invite')); //再次从Cookie里取
        if (!$inviteCode && Session::get('WxOficialAccountOpenId')) { //如果是通过微信授权的，尝试从微信粉丝表里获取推荐人ID
            $wxuser = WxUserModel::find(Session::get('WxOficialAccountOpenId'));
            if ($wxuser && $wxuser->invite) $inviteCode = intval($wxuser->invite);
        }
        return $inviteCode;
    }

    /**
     * 获取推荐员工ID
     * @return int
     */
    protected function getMemberFromAdmin()
    {
        $fromAdmin = 0;
        if (!$fromAdmin) $fromAdmin = intval(Session::get('fromadmin')); //其次从Session里取
        if (!$fromAdmin) $fromAdmin = intval(Request::cookie('fromadmin')); //再次从Cookie里取
        if (!$fromAdmin && Session::get('WxOficialAccountOpenId')) { //如果是通过微信授权的，尝试从微信粉丝表里获取推荐人ID
            $wxuser = WxUserModel::find(Session::get('WxOficialAccountOpenId'));
            if ($wxuser && $wxuser->admin_id) $fromAdmin = intval($wxuser->admin_id);
        }
        return $fromAdmin;
    }
}