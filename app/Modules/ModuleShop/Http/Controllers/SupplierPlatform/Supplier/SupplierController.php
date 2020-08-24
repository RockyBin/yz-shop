<?php

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformAdmin;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformRole;
use App\Modules\ModuleShop\Libs\Utils;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController as BaseController;
use Illuminate\Support\Facades\Hash;
use YZ\Core\Common\VerifyCode;
use YZ\Core\Site\Site;

class SupplierController extends BaseController
{

    public function passwordIsNull()
    {
        try {
            $member = new SupplierPlatformAdmin($this->supplierAdminId, Site::getCurrentSite()->getSiteId());
            $res = $member->passwordIsNull();
            if ($res) {
                return makeApiResponseFail('密码为空需要设置新的密码');
            } else {
                return makeApiResponseSuccess('已存在密码');
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }

    }

    /**
     * 修改登录密码
     * @param Request $request
     * @return array
     */
    public function passwordChange(Request $request)
    {
        try {
            $member = new SupplierPlatformAdmin($this->supplierAdminId, Site::getCurrentSite()->getSiteId());
            // 手机存在，则要通过之前的验证
            $mobile = $member->getModel()->mobile;
            if (empty($mobile)) {
                return makeApiResponseFail(trans("shop-front.member.mobile_set_first"));
            }

            $passwordOld = trim($request->password_old);
            $passwordCheck = $member->passwordCheck($passwordOld);
            if (!$passwordCheck) {
                return makeApiResponse(501, '旧密码不正确');
            }
            $password = trim($request->password);
            $passwordConfirm = trim($request->password_confirm);
            if (empty($password) || $password != $passwordConfirm) {
                return makeApiResponseFail(trans("shop-front.member.password_diff"));
            }

            // 验证密码强度
            if (!Utils::checkPasswordStrength($password)) {
                return makeApiResponseFail(trans("shop-front.member.password_strength"));
            }

            // 修改密码
            SupplierPlatformAdmin::addOrEditStaff(
                [
                    'password' => Hash::make($password),
                    'id' => $this->supplierAdminId
                ]
            );
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 设置新的登录密码
     * @param Request $request
     * @return array
     */
    public function passwordSet(Request $request)
    {
        try {
            $member = new SupplierPlatformAdmin($this->supplierAdminId, Site::getCurrentSite()->getSiteId());
            // 手机存在，则要通过之前的验证
            $mobile = $member->getModel()->mobile;
            if (empty($mobile)) {
                return makeApiResponseFail(trans("shop-front.member.mobile_set_first"));
            }

            // 验证验证码
            $code = $request->input('code');
            if (!$code) {
                return makeApiResponseFail(trans("shop-front.common.verify_code_fail"), ['code_error' => true]);
            }
            $verifyCodeResult = VerifyCode::checkSmsCode($mobile, $code);
            if (intval($verifyCodeResult['code']) != 200) {
                $returnData = $verifyCodeResult['data'];
                $returnData['code_error'] = true;
                return makeApiResponse(502, $verifyCodeResult['msg'], $returnData);
            }

            $password = trim($request->password);

            // 验证密码强度
            if (!Utils::checkPasswordStrength($password)) {
                return makeApiResponseFail(trans("shop-front.member.password_strength"));
            }
            // 修改密码
            SupplierPlatformAdmin::addOrEditStaff(
                [
                    'password' => Hash::make($password),
                    'id' => $this->supplierAdminId
                ]
            );
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function save(Request $request)
    {
        try {
            if ($request->id) $params['id'] = $request->id;
            if (!$request->name) return makeApiResponseFail('请输入姓名');
            else $params['name'] = $request->name;

            if (!$request->mobile) return makeApiResponseFail('请输入手机号');
            else $params['mobile'] = $request->mobile;

            $params['role_id'] = $request->role_id;

            if ($request->password) $params['password'] = Hash::make($request->password);
            if ($request->status) $params['status'] = $request->status;
            if ($request->headurl) $params['headurl'] = $request->headurl;
            else  $params['headurl'] = null;
            // 所在的哪个供应商
            $params['member_id'] = $this->memberId;
            SupplierPlatformAdmin::addOrEditStaff($params);
            return makeApiResponseSuccess('添加成功');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getList(Request $request)
    {
        try {
            $params = $request->toArray();
            $params['member_id'] = $this->memberId;
            $list = SupplierPlatformAdmin::getList($params);
            $supplierRole = new SupplierPlatformRole();
            $supplierRoleData = $supplierRole->getList(['show_all' => true, 'member_id' => $this->memberId]);
            $list['role_list'] = $supplierRoleData['list'];

            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function editStatus(Request $request)
    {
        try {
            if (!$request->id) return makeApiResponseFail('请填入正确的ID');
            if (!isset($request->status)) return makeApiResponseFail('请输入状态');
            $params['status'] = $request->status;
            $params['id'] = $request->id;
            SupplierPlatformAdmin::addOrEditStaff($params);
            return makeApiResponseSuccess('添加成功');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getInfo(Request $request)
    {
        try {
            $data['admin'] = SupplierPlatformAdmin::getInfo($request->id);
            // 展示权限列表

            $supplierRole = new SupplierPlatformRole();
            $supplierRoleData = $supplierRole->getList(['show_all' => true, 'member_id' => $this->memberId]);
            $data['role_list'] = $supplierRoleData['list'];

            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function checkMobile(Request $request)
    {
        try {
            $id = $request->id ?? 0;
            $member_id = $this->memberId;
            $res = SupplierPlatformAdmin::checkMobile($request->mobile, $id, $member_id);
            if (!$res) {
                return makeApiResponseSuccess('ok', $res);
            } else {
                return makeApiResponseFail('此手机号码已被注册');
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function checkUsername(Request $request)
    {
        try {
            $id = $request->id ?? 0;
            $member_id = $this->memberId;
            $res = SupplierPlatformAdmin::checkUsername($request->username, $id, $member_id);
            if (!$res) {
                return makeApiResponseSuccess('ok', $res);
            } else {
                return makeApiResponseFail('此登录账号已被注册');
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 上传头像
     * @param Request $request
     * @return array
     */
    public function uploadHeadImage(Request $request)
    {
        try {
            $headUrl = SupplierPlatformAdmin::uploadHeadImage($request->file('head_image'));
            return makeApiResponseSuccess('ok', ['headurl' => $headUrl]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 刪除員工
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            SupplierPlatformAdmin::delete($request->id);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}
