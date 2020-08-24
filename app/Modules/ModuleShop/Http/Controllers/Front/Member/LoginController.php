<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use Illuminate\Support\Facades\Request;
use YZ\Core\Member\Auth;
use YZ\Core\Member\Fans;
use YZ\Core\Site\Config;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Utils;
use App\Http\Controllers\Member\LoginController as LoginBaseController;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use Illuminate\Support\Facades\Session;
use YZ\Core\Model\WxUserModel;

/**
 * 登录类
 * Class LoginController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member
 */
class LoginController extends LoginBaseController
{
    protected $urlBase = '/shop/member/login'; // 覆写当前根路径
    protected $afterLogin = '/shop/front/#/'; // 覆写登录后跳转的地址
    protected $afterRegister = '/shop/front/#/'; // 覆写注册后跳转的地址
    protected $bindPageUrl = '/shop/front/#/users/bind-mobile'; // 覆写绑定用户
    private $siteId = 0;

    /**
     * 初始化
     * LoginController constructor.
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function __construct()
    {
        parent::__construct();
        $this->siteId = Site::getCurrentSite()->getSiteId();
    }

    /**
     * 登录页面
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return view('moduleshop::Member/Login/index');
    }

    /**
     * 注册页面
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function register()
    {
        return view('moduleshop::Member/Login/register', [
            'type' => Request::input('type')
        ]);
    }

    /**
     * 绑定手机号码
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showBind()
    {
        return view('moduleshop::Member/Login/bind', [
            'type' => Request::input('type')
        ]);
    }

    /**
     * 绑定的接口
     * @return array|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function doBind()
    {
        $oldMemberId = Auth::hasLogin();
        $result = parent::doBind();
        if (is_array($result) && $result['code'] == 200) {
            $member = new Member(Auth::hasLogin());
            // 处理帐号合并
            if($oldMemberId != $member->getModel()->id){
                Member::mergeMember($oldMemberId, $member->getModel()->id);
            }
            // 是否需要设置密码(2020/4/16改为不再要求密码)
            //$result['data']['password_need_set'] = $member->passwordIsNull();
        }
        return $result;
    }

    /**
     * 获取配置信息
     * @return array
     */
    public function getConfig()
    {
        $inviteCodeShow = 0; // 是否显示邀请码(0:隐藏 1:显示非必填 2：显示且必填 )
        $registerProtocolShow = false; // 是否显示注册协议
        $registerProtocolContent = ''; // 注册协议内容

        // 注册协议
        $shopConfig = new ShopConfig();
        $shopConfigData = $shopConfig->getInfo();
        if ($shopConfigData && $shopConfigData['info']) {
            $shopConfigModel = $shopConfigData['info'];
            if ($shopConfigModel->register_isshow) {
                $registerProtocolShow = true;
                $registerProtocolContent = $shopConfigModel->register_protocol;
            }
        }

        // 是否显示推荐码
        $settingModel = Site::getCurrentSite()->getConfig()->getModel();
        if ($settingModel) {
            $inviteCodeShow = $settingModel->show_code;
        }

        return makeApiResponseSuccess('ok', [
            'invite_code_show' => $inviteCodeShow,
            'register_protocol_show' => $registerProtocolShow,
            'register_protocol_content' => $registerProtocolContent
        ]);
    }

    /**
     * 重置密码
     * @return array
     */
    public function resetPassword()
    {
        try {
            $memberId = Auth::hasLogin();
            if ($memberId) {
                $password = trim(Request::get('password'));
                $passwordConfirm = trim(Request::get('password_confirm'));
                if (empty($password) || $password != $passwordConfirm) {
                    return makeApiResponseFail(trans("shop-front.member.password_diff"));
                }
                // 检查密码强度
                if (!Utils::checkPasswordStrength($password)) {
                    return makeApiResponseFail(trans("shop-front.member.password_strength"));
                }
                $member = new Member($memberId);
                // 这个方法指给未设置过密码的情况使用
                if (!$member->passwordIsNull()) {
                    return makeApiResponseFail(trans("shop-front.member.password_exist"));
                }
                // 修改密码
                $result = $member->edit([
                    'password' => $password
                ]);
                if ($result['code'] == 200) {
                    $data = ['member_id' => $member->getMemberId()];
                    $redirect = Request::get('loginRedirect');
                    if (!$redirect) $redirect = Session::get('loginRedirect');
                    if ($redirect) $data['redirect'] = $redirect;
                    return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $data);
                } else {
                    return makeApiResponseFail(trans("shop-front.common.action_fail"));
                }
            } else {
                return makeApiResponseFail(trans("shop-front.member.login_need"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 返回当前登录用户的信息
     *
     * @return void
     */
    public function getInfo()
    {
        try {
            $memberId = Auth::hasLogin();
            if ($memberId) {
                return makeApiResponseSuccess(trans("shop-front.common.action_ok"), [
                    'member_id' => $memberId
                ]);
            } else {
                return makeApiResponseFail(trans("shop-front.member.login_need"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 覆写用户登录方法
     * @param \YZ\Core\Member\Member $memberData
     * @return bool|void
     * @throws \Exception
     */
    protected function memberLogin($memberData)
    {
        $member = new Member($memberData);
        return $member->login();
    }

    /**
     * 覆写用户注册方法
     * @param $userInfo
     * @param $regFrom
     * @return void|\YZ\Core\Member\Member
     */
    protected function memberRegister($userInfo, $regFrom)
    {
        $param = (array)$userInfo;
        $param['regfrom'] = $regFrom;
        $param['terminal_type'] = getCurrentTerminal();
        $member = new Member(0, $this->siteId);
        $member->add($param);
        return $member->getMember();
    }

    /**
     * 覆写检查密码
     * @param $memberData
     * @param $password
     * @return bool
     */
    protected function passwordCheck($memberData, $password)
    {
        $member = new Member($memberData);
        return $member->passwordCheck($password);
    }

    /**
     * 绑定上下级
     * @param $memberData
     * @param $inputParams
     * @param $showCode
     * @throws \Exception
     */
    protected function memberInvite($memberData, $inputParams)
    {
        $member = new Member($memberData);
        // 处理会员推荐关系
        if(Site::getCurrentSite()->getConfig()->getModel()->bind_invite_time == 0) {
            $inviteCode = intval($inputParams['invite_code']); //优先从提交参数里取
            if ($inviteCode) {
                $mcheck = new Member($inviteCode);
                if (!$mcheck->checkExist()) {
                    $inviteCode = 0; //当填写的邀请码不存在时，从 session 、cookie 里重新读
                }
            }
            if (!$inviteCode) {
                $inviteCode = $this->getMemberInvite();
            }

            if ($inviteCode > 0) {
                $member->setParent($inviteCode);
            }
        }

        // 处理员工推荐关系
        $fromAdmin = $this->getMemberFromAdmin();
        $member->setFromAdmin($fromAdmin);
    }


    public function showMemberInvite()
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $config = (new Config($siteId))->getModel();
        // 有推荐人的，就不需要显示，把show_code置为0即可，前端做显示隐藏处理
        if ($this->getMemberInvite()) $config->show_code = 0;
        return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $config->show_code);
    }
}