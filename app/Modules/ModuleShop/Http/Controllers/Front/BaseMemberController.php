<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController as BaseController;
use YZ\Core\Member\Auth;
use Illuminate\Support\Facades\Session;
use YZ\Core\Member\Member;
use Illuminate\Support\Facades\Route;

/**
 * 会验证用户是否已经登录，未登录会直接返回Json
 * 需要验证登录的Controller可继承此类
 * Class BaseMemberController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member
 */
class BaseMemberController extends BaseController
{
    protected $memberId = 0; // 会员id

    /**
     * 初始化
     * BaseMemberController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        // 如果没有登录，则做一些事情
        // 在正常环境里，在 __construct 里是取不到session的，要在 middleware() 里取
        // 但在 swoole 里，第一次执行 __construct() 是取不到，因为这时 StartSession 中间件还没启动，
        // 第二次执行时，在 __construct() 里是可以取到的，因为 StartSession 已经启动过了，如果这时还进入
        // middleware()，可能是 StartSession 又执行了，会导致 $this->memberId 丢失
        $this->memberId = Auth::hasLogin();
        if (!$this->memberId) {
            $this->middleware(function ($request, Closure $next) {
                $this->memberId = Auth::hasLogin();
                if (!$this->IsLogin()) {
                    return $this->actionWhenNoLogin();
                }
                if ($this->needBindMobile()){
                    return $this->actionWhenNoMobile();
                }
                return $next($request);
            });
        }
    }

    /**
     * 未登录时的行为
     * @return JsonResponse
     */
    protected function actionWhenNoLogin()
    {
        $data = makeApiResponse(403, trans('shop-front.member.login_need'), [
            'redirect' => $this->getLoginUrl(),
        ]);

        return new JsonResponse($data);
    }

    /**
     * 未绑定手机时的行为
     * @return JsonResponse
     */
    protected function actionWhenNoMobile()
    {
        $data = makeApiResponse(409, '请先绑定手机', [
            'redirect' => $this->getBindMobileUrl(),
        ]);

        return new JsonResponse($data);
    }

    /**
     * 是否登录
     * @return bool
     */
    protected function IsLogin()
    {
        if ($this->memberId) {
            //获取用户相关信息
            $member = new member($this->memberId);
            $member_model = $member->getModel();
            //如果用户账户是封号状态，则强制退出
            if (!$member_model->status) {
                Auth::logout();
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取登录地址
     * @param string $loginRedirect
     * @return string
     */
    public function getBindMobileUrl($loginRedirect = '')
    {
        if (empty($loginRedirect)) $loginRedirect = Request::get('loginRedirect', '');
        if (empty($loginRedirect)) $loginRedirect = Session::get('loginRedirect');
        if (empty($loginRedirect)) $loginRedirect = '/';
        $loginUrl = '/shop/front/#/users/bind-mobile';
        return Auth::getLoginUrl($loginUrl, $loginRedirect);
    }

    /**
     * 判断是否已绑定手机
     * @return bool
     */
    protected function needBindMobile()
    {
        if ($this->memberId) {
            $member = new Member($this->memberId);
            $memberModel = $member->getModel();
            //如果用户账户是封号状态，则强制退出
            if (!$memberModel->mobile || !preg_match('/^\d{11}$/',$memberModel->mobile)) {
                $action = Route::currentRouteAction();
                $action = strtolower($action);
                if (substr($action, 0, 1) == '\\') $action = substr($action, 1);
                $config = config('requiremobile');
                foreach ($config as $item) {
                    $item = trim(strtolower($item));
                    $item = ltrim($item, '\\');
                    if($item == $action){
                        return true;
                    }
                }
            }
        } else {
            return true;
        }
    }
}