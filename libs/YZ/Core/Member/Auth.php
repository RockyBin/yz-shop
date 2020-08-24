<?php
//phpcodelock
namespace YZ\Core\Member;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberAuthModel;
use YZ\Core\Member\ThirdLogin\QQLogin;
use YZ\Core\Member\ThirdLogin\AlipayLogin;
use EasyWeChat\Factory;
use YZ\Core\Weixin\WxApp;
use YZ\Core\Weixin\WxWork;

/**
 * 会员授权/登录类
 * Class Auth
 * @package YZ\Core\Member
 */
class Auth
{
    /**
     * 发起微信公众号授权登录
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public static function wxLogin($redirectUrl)
    {
        $site = Site::getCurrentSite();
        $options = [
            'app_id' => $site->getOfficialAccount()->getConfig()->getModel()->appid,
            'secret' => $site->getOfficialAccount()->getConfig()->getModel()->appsecret,
        ];

        $app = Factory::officialAccount($options);
        $response = $app->oauth->scopes(['snsapi_userinfo'])->setRedirectUrl($redirectUrl)->redirect();
        return $response;
    }

    /**
     * 发起企业微信授权登录
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public static function wxWorkLogin($redirectUrl)
    {
        $wxWork = new WxWork();
        $response = $wxWork->OAuth($redirectUrl);
        return $response;
    }

    /**
     * 获取公众号粉丝的信息
     * @return AuthUserInfo
     */
    public static function getWxUserInfo(): AuthUserInfo
    {
        $site = Site::getCurrentSite();
        $options = [
            'app_id' => $site->getOfficialAccount()->getConfig()->getModel()->appid,
            'secret' => $site->getOfficialAccount()->getConfig()->getModel()->appsecret,
        ];

        $app = Factory::officialAccount($options);
        $user = $app->oauth->user();
        /*$user->getId();  // 对应微信的 OPENID
        $user->getNickname(); // 对应微信的 nickname
        $user->getName(); // 对应微信的 nickname 因为系统目前名字需要自定义，所以不再读取
        $user->getAvatar(); // 头像网址
        $user->getOriginal(); // 原始API返回的结果
        $user->getToken(); // access_token， 比如用于地址共享时使用
        */
        $userInfo = $user->getOriginal();
        $userInfo2 = $app->user->get($user->getId());
        $userInfo['subscribe'] = $userInfo2['subscribe'];
        $userInfo['subscribe_time'] = $userInfo2['subscribe_time'];
        $userInfo['unsubscribe_time'] = $userInfo2['unsubscribe_time'];
        \YZ\Core\Weixin\WxUserHelper::saveUserInfo($userInfo);
        return new AuthUserInfo($user->getId(), $user->getNickname(), null, $user->getAvatar(), $user->getToken()->toArray());
    }

    /**
     * 获取企业微信用户的信息
     * @return AuthUserInfo
     */
    public static function getWxWorkUserInfo(): AuthUserInfo
    {
        $wxWork = new WxWork();
        $user = $wxWork->afterOAuth();
        return new AuthUserInfo($user['id'], $user['nick_name'], $user['name'], $user['thumb_avatar'], ['is_extenal' => $user['is_extenal']]);
    }

    /**
     * 获取微信小程序用户的信息
     * @return AuthUserInfo
     */
    public static function getWxAppUserInfo(): AuthUserInfo
    {
        $wxApp = new WxApp();
        $user = $wxApp->login(Request::get('code'));
		if(!$user['expires_in']) $user['expires_in'] = 7200;
        if(!$user['nick_name']) $user['nick_name'] = '小程序用户_'.randString(4);
        //if(!$user['name']) $user['name'] = $user['nick_name'];
        $userInfo = new AuthUserInfo($user['openid'], $user['nick_name'], $user['name'], $user['thumb_avatar'],['session_key' => $user['session_key'],'expires_in' => $user['expires_in']]);
        Cache::set($user['session_key'],$userInfo,intval($user['expires_in']/60)); //将小程序的session信息存入缓存中,后面在获取手机登录时会用到
        return $userInfo;
    }

    /**
     * 跳转到QQ网站进行QQ登录
     * @param $redirectUrl
     * @return string
     */
    public static function qqLogin($redirectUrl)
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getModel();
        $qqLogin = new QQLogin($config->qq_appid, $config->qq_appsecret, $redirectUrl);
        return $qqLogin->getLogonUrl();
    }

    /**
     * QQ登陆获取用户信息
     * @return AuthUserInfo
     * @throws \Exception
     */
    public static function getQqUserInfo($redirectUrl): AuthUserInfo
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getModel();
        $qqLogin = new QQLogin($config->qq_appid, $config->qq_appsecret, $redirectUrl);
        if (!request("code")) throw new \Exception("qq login fail: missing code");

        $code = request("code"); //token
        $state = request("state"); //状态

        $qqLogin->setCode($code);
        $qqLogin->setState($state);

        //此处可认为用户是登陆QQ成功的
        $qqLogin->getToken();
        //获取openid
        $qqLogin->getOpenId2();
        $openid = $qqLogin->getOpenId();
        if (!$openid) throw new \Exception("qq login fail: can not get openid");

        $qqUserInfo = $qqLogin->GetUserInfo();

        return new AuthUserInfo($openid, $qqUserInfo["nickname"], $qqUserInfo["name"] ? $qqUserInfo["name"] : 'QQ用户_' . substr(md5(mt_rand()), 0, 6), $qqUserInfo["avatar"]);
    }

    /**
     * 跳转到支付宝网站进行授权登录
     * @param $redirectUrl
     * @return string
     */
    public static function alipayLogin($redirectUrl)
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getPayConfig();
        $aliLogin = new AlipayLogin($config->alipay_appid, $config->alipay_public_key, $config->alipay_private_key);
        return $aliLogin->getLoginUrl($redirectUrl);
    }

    /**
     * 获取支付宝用户信息
     * @param $auth_code
     * @return AuthUserInfo
     * @throws \Exception
     */
    public static function alipayGetUerInfo($auth_code)
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getPayConfig();
        $aliLogin = new AlipayLogin($config->alipay_appid, $config->alipay_public_key, $config->alipay_private_key);
        $userinfo = $aliLogin->getUserInfo($auth_code);
        $defaultName = '支付宝用户_' . substr(md5(mt_rand()), 0, 6);
        return new AuthUserInfo($userinfo['user_id'], $userinfo["nick_name"] ? $userinfo["nick_name"] : $defaultName, $userinfo["nick_name"] ? $userinfo["nick_name"] : $defaultName, $userinfo["avatar"]);
    }

    /**
     * 尝试使用微信公众号登录
     * @param $openid
     * @return bool|Member
     */
    public static function getMemberWxOficialAccount($openid)
    {
        Session::put('WxOficialAccountOpenId', $openid);
        return static::getMemberWithAuthAccount(\YZ\Core\Constants::MemberAuthType_WxOficialAccount, $openid);
    }

    /**
     * 尝试使用企业微信登录
     * @param $openid , 当授权方是外部联系人时，使用的是外部联系人的 OpenId，当是企业成员时，使用的是企业成员的 UserId
     * @return bool|Member
     */
    public static function getMemberWxWorkAccount($openid)
    {
        Session::put('WxWorkAccountId', $openid);
        return static::getMemberWithAuthAccount(\YZ\Core\Constants::MemberAuthType_WxWork, $openid);
    }

    /**
     * 尝试使用微信小程序登录
     * @param $openid , 小程序 openid
     * @return bool|Member
     */
    public static function getMemberWxAppAccount($openid)
    {
        Session::put('WxAppAccountId', $openid);
        return static::getMemberWithAuthAccount(\YZ\Core\Constants::MemberAuthType_WxApp, $openid);
    }

    /**
     * 尝试使用支付宝帐号登录
     * @param $openid
     * @return bool|Member
     */
    public static function getMemberAlipayAccount($openid)
    {
        Session::put('AlipayUserId', $openid);
        return static::getMemberWithAuthAccount(\YZ\Core\Constants::MemberAuthType_Alipay, $openid);
    }

    /**
     * 尝试使用QQ帐号登录
     * @param $openid
     * @return bool|Member
     */
    public static function getMemberQqAccount($openid)
    {
        Session::put('QqOpenId', $openid);
        return static::getMemberWithAuthAccount(\YZ\Core\Constants::MemberAuthType_QQ, $openid);
    }

    /**
     * 判断是否已经登录，如果已经登录，返回会员ID，否则返回0
     * @return mixed
     */
    public static function hasLogin()
    {
        $memberId = Session::get('memberId');
        return $memberId;
    }

    /**
     * 会员登出
     */
    public static function logout()
    {
        // 移除会员信息
        Session::remove('memberId');
    }

    /**
     * 在按需登录的地址，自动跳转到登录页
     * @param $loginUrl 登录地址
     * @param null $loginRedirect 登陆后回条地址
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public static function goLogin($loginUrl, $loginRedirect = null)
    {
        return redirect(self::getLoginUrl($loginUrl, $loginRedirect));
    }

    /**
     * 登录地址添加回调地址参数
     * @param $loginUrl 登录地址
     * @param null $loginRedirect 登陆后回条地址
     * @return string
     */
    public static function getLoginUrl($loginUrl, $loginRedirect = null)
    {
        if (!$loginRedirect) {
            $loginRedirect = Request::getRequestUri();
            // 把post的也放进去
            $postArray = [];
            foreach ($_POST as $key => $val) {
                $postArray[$key] = $val;
            }
            if (count($postArray) > 0) {
                $loginRedirect .= (strpos($loginRedirect, '?') !== false ? '&' : '?') . implode('&', $postArray);
            }
        }
        // 把回调地址写入Session，以便某些场景下前端无需对 loginRedirect 进行传递处理
        if (!empty($loginRedirect)) {
            $loginRedirect = str_replace('?from=singlemessage#', '#', $loginRedirect); // 去掉微信自动添加的信息
            Session::put('loginRedirect', $loginRedirect);
        }
        $url = $loginUrl . '?loginRedirect=' . urlencode($loginRedirect);
        return $url;
    }

    /**
     * 通过第三方信息获取会员信息
     * @param $type
     * @param $openid
     * @return bool|Member
     */
    private static function getMemberWithAuthAccount($type, $openid)
    {
        $auth = MemberAuthModel::where('type', $type)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('openid', $openid)->first();
        if ($auth) {
            $auth->lastlogin = date('Y-m-d H:i:s');
            $auth->save();
            $member = new Member($auth->member_id);
            if ($member->checkExist()) {
                return $member;
            }
        }
        return false;
    }
}