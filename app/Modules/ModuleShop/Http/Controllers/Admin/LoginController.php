<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin;

use Illuminate\Http\Request;
use YZ\Core\Constants;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\Site\Site;


/**
 * 管路员登录
 * Class LoginController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin
 */
class LoginController extends BaseAdminController
{
    /**
     * 登录
     * @param Request $request
     * @return array
     */
    public function login(Request $request)
    {
        try {
            $username = trim($request->username);
            $password = trim($request->password);
            // 验证站点状态
            if (!in_array(Site::getCurrentSite()->getModel()->status,[Constants::SiteStatus_Active,Constants::SiteStatus_Try])){
                return makeApiResponseFail("站点未生效，不能登录");
            }
            // 验证站点过期时间
            if (strtotime(Site::getCurrentSite()->getModel()->expiry_at) < time()){
                return makeApiResponseFail("站点已过期，不能登录");
            }
            // 先验证是否被冻结
            $siteAdmin = SiteAdmin::getByUserName($username);
            if (!$siteAdmin) {
                return makeApiResponseFail(trans('shop-admin.admin.not_exist'));
            } else if ($siteAdmin->status != Constants::SiteAdminStatus_Active) {
                return makeApiResponseFail(trans('shop-admin.admin.freeze'));
            }
            $result = SiteAdmin::login($username, $password);
            if ($result) {
                $data = SiteAdmin::getLoginedAdmin();
                return makeApiResponseSuccess(trans('shop-admin.admin.login_ok'), $data);
            } else {
                return makeApiResponseFail(trans('shop-admin.admin.password_error'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 用于主站和本系统的一键登录
     *
     * @param Request $request
     * @return void
     */
    public function autologin(Request $request){
        if(\YZ\Core\License\SNUtil::isPlatformVersion()){
            $loginTime = $request->get("loginTime"); //timespam;
            $siteid = $request->get("InitSiteID");
            $HashKey = $request->get("HashKey");
            if(!$siteid) $siteid = Site::getCurrentSite()->getSiteId();
            $key = config("app.API_PASSWORD");
            $secKey = MD5($key . $loginTime . $siteid);
            // 验证站点状态
            if (!in_array(Site::getCurrentSite()->getModel()->status,[Constants::SiteStatus_Active,Constants::SiteStatus_Try])){
                return makeApiResponseFail("站点未生效，不能登录");
            }
            // 验证站点过期时间
            if (strtotime(Site::getCurrentSite()->getModel()->expiry_at) < time()){
                return makeApiResponseFail("站点已过期，不能登录");
            }
            if ($secKey == $HashKey) {
                $siteAdmin = SiteAdmin::getByUserName("admin");
                if (!$siteAdmin) {
                    return makeApiResponseFail(trans('shop-admin.admin.not_exist'));
                } elseif (!$siteAdmin->status) {
                    return makeApiResponseFail(trans('shop-admin.admin.freeze'));
                }
                $result = SiteAdmin::login("admin", "", 0);
                $redir = getHttpProtocol()."://".getHttpHost()."/shop/admin/";
                header("Location: $redir");
            }else{
                return makeApiResponseError(new \Exception("checksum error"));
            }
        }else{
            return makeApiResponseError(new \Exception("未平台版本不支持一键登录"));
        }
    }

    /**
     * 登出
     * @return array
     */
    public function logout()
    {
        try {
            SiteAdmin::logout();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}