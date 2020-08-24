<?php
/**
 * 前台基础类
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front;

use Closure;
use Illuminate\Support\Facades\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Auth;
use YZ\Core\Model\SiteAdminModel;
use YZ\Core\Model\WxWorkModel;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberModel;
use YZ\Core\Weixin\WxConfig;

class BaseFrontController extends BaseController
{
    protected $siteId = 0; // 站点id

    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
        // session要在中间件里才生效,在 swoole 环境下，用中间件会出现 session cookie 被清空的情况，先移到 beforeAction 做处理
        /*$this->middleware(function ($request, Closure $next) {
            // 保存登录回跳地址
            if (Request::get('loginRedirect')) {
                $loginRedirect = Request::get('loginRedirect');
                $loginRedirect = str_replace('?from=singlemessage#', '#', $loginRedirect); // 去掉微信自动添加的信息
                Session::put('loginRedirect', $loginRedirect);
            }
            // 保存分销来源，因为使用中间件，在swoole下会有点问题，注意 
            if (intval(Request::get("invite"))) {
                $invite = intval(Request::get("invite"));
                // 若与当前登录用户一致则不记录
                if (!Auth::hasLogin() || intval(Auth::hasLogin()) != $invite) {
                    $memberModel = MemberModel::query()->where('site_id', $this->siteId)->where('id', $invite)->first();
                    if ($memberModel) {
                        // 推荐会员存在才保存
                        Session::put('invite', $invite);
                        Cookie::queue('invite', $invite, 0, '/', null, null, false, false);
                        Cookie::queue('invite_nick_name', $memberModel->nickname, 0, '/', null, null, false, false);
                    }
                }
            }
            return $next($request);
        });*/
    }

	public function beforeAction($action = ''){
		// 保存登录回跳地址
		if (Request::get('loginRedirect')) {
			$loginRedirect = Request::get('loginRedirect');
			$loginRedirect = str_replace('?from=singlemessage#', '#', $loginRedirect); // 去掉微信自动添加的信息
			Session::put('loginRedirect', $loginRedirect);
		}
		// 保存分销来源
		if (intval(Request::get("invite"))) {
			$invite = intval(Request::get("invite"));
			// 若与当前登录用户一致则不记录
			if (!Auth::hasLogin() || intval(Auth::hasLogin()) != $invite) {
				$memberModel = MemberModel::query()->where('site_id', $this->siteId)->where('id', $invite)->first();
				if ($memberModel) {
					// 推荐会员存在才保存
					Session::put('invite', $invite);
					Cookie::queue('invite', $invite, 0, '/', null, null, false, false);
					Cookie::queue('invite_nick_name', $memberModel->nickname, 0, '/', null, null, false, false);
				}
			}
		}
        // 保存员工推荐来源
        if (intval(Request::get("fromadmin"))) {
            $fromadmin = intval(Request::get("fromadmin"));
            $adminModel = SiteAdminModel::query()->where('site_id', $this->siteId)->where('id', $fromadmin)->first();
            if ($adminModel) {
                Session::put('fromadmin', $fromadmin);
                Cookie::queue('fromadmin', $fromadmin, 0, '/', null, null, false, false);
                Cookie::queue('fromadmin_name', $adminModel->name, 0, '/', null, null, false, false);
            }
        }
	}

    public function callAction($method, $parameters){
		if(method_exists($this,'beforeAction')) call_user_func_array([$this, 'beforeAction'], ['action' => $method]);
		$return = parent::callAction($method, $parameters);
		if(method_exists($this,'afterAction')) call_user_func_array([$this, 'afterAction'], ['action' => $method]);
		return $return;
	}

    /**
     * 获取登录地址
     * @param string $loginRedirect
     * @return string
     */
    public function getLoginUrl($loginRedirect = '')
    {
        if (empty($loginRedirect)) $loginRedirect = Request::get('loginRedirect', '');
        if (empty($loginRedirect)) $loginRedirect = Session::get('loginRedirect');
        if (empty($loginRedirect)) $loginRedirect = '/';
        // 微信，直接授权登录
        $loginUrl = '/shop/front/#/users/login';
        if (getCurrentTerminal() == Constants::TerminalType_WxOfficialAccount) {
            $wxInfo = new WxConfig();
            if($wxInfo->infoIsFull()) $loginUrl = '/shop/member/login/wxlogin';
        }
        if (getCurrentTerminal() == Constants::TerminalType_WxWork) {
            $wxWork = WxWorkModel::query()->where(['site_id' => $this->siteId,'status' => 1])->first();
            if($wxWork) $loginUrl = '/shop/member/login/wxlogin/wxwork';
        }
        return Auth::getLoginUrl($loginUrl, $loginRedirect);
    }

    /**
     * 跳转到登录地址
     * @param string $loginRedirect
     * @return \Illuminate\Http\RedirectResponse
     */
    public function goLogin($loginRedirect = '')
    {
        return \Redirect::to($this->getLoginUrl($loginRedirect));
    }
}