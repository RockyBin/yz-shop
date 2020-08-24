<?php


namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform;

use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierAdminModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierRolePermModel;
use Illuminate\Http\Request;
use YZ\Core\Common\VerifyCode;
use YZ\Core\Constants;
use YZ\Core\Site\Site;
use Illuminate\Routing\Controller as BaseController;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformAdmin;

/**
 * 供应商登录
 * Class LoginController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin
 */
class LoginController extends BaseController
{
    /**
     * 登录
     * @param Request $request
     * @return array
     */
    public function login(Request $request)
    {
        try {
            // 暂时以手机号码作为登录账号，以后可能有其他的登录方式
            $mobile = trim($request->username);
            $code = trim($request->code);
            $type = $request->type;
            $password = trim($request->password);
            // 验证站点状态
            if (!in_array(Site::getCurrentSite()->getModel()->status, [Constants::SiteStatus_Active, Constants::SiteStatus_Try])) {
                return makeApiResponseFail("站点未生效，不能登录");
            }
            // 验证站点过期时间
            if (strtotime(Site::getCurrentSite()->getModel()->expiry_at) < time()) {
                return makeApiResponseFail("站点已过期，不能登录");
            }
            // 先检测这个供应商是否存在且生效
            SupplierPlatformAdmin::checkSupplier($mobile);
            // 先验证是否被冻结
            $SupplierPlatformAdmin = SupplierPlatformAdmin::getByMobile($mobile);
            if (!$SupplierPlatformAdmin) {
                return makeApiResponseFail('该账号不存在');
            }

            if ($SupplierPlatformAdmin->status != Constants::MemberStatus_Active) {
                return makeApiResponseFail(trans('shop-admin.admin.freeze'));
            }
            if ($type == 1) {
                $result = SupplierPlatformAdmin::loginByMobile($mobile, $password);
            } else if ($type == 2) {
                $verifyCodeResult = VerifyCode::checkSmsCode($mobile, $code);
                if (intval($verifyCodeResult['code']) != 200) {
                    return makeApiResponse($verifyCodeResult['code'], $verifyCodeResult['msg'], $verifyCodeResult['data']);
                } else {
                    $result = SupplierPlatformAdmin::loginWithIdOrModel($SupplierPlatformAdmin);
                }
            }
            if ($result) {
                $data = SupplierPlatformAdmin::getLoginedSupplierPlatformAdminId();
                return makeApiResponseSuccess(trans('shop-admin.admin.login_ok'), $data);
            } else {
                return makeApiResponseFail(trans('shop-admin.admin.password_error'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 登出
     * @return array
     */
    public function logout()
    {
        try {
            SupplierPlatformAdmin::logout();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}