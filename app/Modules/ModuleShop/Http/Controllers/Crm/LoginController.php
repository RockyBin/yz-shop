<?php

namespace App\Modules\ModuleShop\Http\Controllers\Crm;

use App\Modules\ModuleShop\Libs\Crm\Auth;
use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\Site\SiteAdminAllocation;

class LoginController extends BaseController
{
    /**
     * 小程序授权登录
     * @return array
     */
    public function miniAppAuth(Request $request)
    {
        try {
            $params = $request->all();
            $res = Auth::wxAppAuth($params['code']);
            return makeApiResponseSuccess('ok', $res);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 小程序授权登录，用于分享
     * @return 返回session_key等信息
     */
    public function wxAppAuthGetMobile(Request $request)
    {
        try {
            $params = $request->toArray();
            $res = Auth::wxAppAuthGetMobile($params['code']);
            if ($res) {
                return makeApiResponseSuccess('ok', $res);
            } else {
                return makeApiResponseFail('获取失败，请重新获取', $res);
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 小程序端切换管理帐户
     * @return array
     */
    public function miniAppSwitchAdmin(Request $request)
    {
        try {
            $params = $request->all();
            $res = Auth::wxAppSwitchAdmin($params['auth_id']);
            return makeApiResponseSuccess('ok', $res);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 小程序端登录管理用户
     * @param Request $request
     */
    public function login(Request $request)
    {
        try {
            $params = $request->all();
            $res = Auth::login($params['site_id'], $params['username'], $params['password'], $params['openid'], $params['headurl']);
            return makeApiResponseSuccess('ok', $res);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    function add(Request $request)
    {
        if (strlen($request->input('mobile')) != 11) {
            return makeApiResponseFail('手机号码格式不正确，请重新输入');
        }
        $member = new Member(0, Site::getCurrentSite()->getSiteId(), false);
        $param = $request->toArray();
        if ($request->has('admin_id')) {
            $param['admin_id'] = $request->input('admin_id');
            $siteAdminModel = (new SiteAdmin($param['admin_id']))->getModel();
            if ($siteAdminModel->status != 1) {
                $param['admin_id'] = (new SiteAdminAllocation())->allocate();
            }
        }
        $result = $member->add($param);
        if ($result['code'] == 200) {
            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $result['data']);
        } else {
            return makeApiResponse($result['code'], $result['msg'], $result['data']);
        }
    }

    function edit(Request $request)
    {
        try {
            if (!$request->member_id) {
                return makeApiResponseFail('请传输正确的member_id');
            }
            $member = new Member($request->member_id, Site::getCurrentSite()->getSiteId());
            if ($request->has("nickname")) {
                $params['nickname'] = $request->nickname;
            }
            if ($request->has("headurl")) {
                $params['headurl'] = $request->headurl;
            }
            $member->edit($params);
            return makeApiResponseSuccess('ok');

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

}