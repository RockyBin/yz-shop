<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Site;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController as BaseController;
use App\Modules\ModuleShop\Libs\License\SN;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use App\Modules\ModuleShop\Libs\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use YZ\Core\Common\Export;
use YZ\Core\Constants;
use YZ\Core\License\SNUtil;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\Site\SiteAdminDepartment;
use YZ\Core\Site\SiteRole;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;

/**
 * 管理员
 * Class SiteAdminController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Site
 */
class SiteAdminController extends BaseController
{
    /**
     * 修改当前用户密码
     * @param Request $request
     * @return array
     */
    public function password(Request $request)
    {
        try {
            $curAdminData = SiteAdmin::getLoginedAdmin();
            $siteAdmin = new SiteAdmin(intval($curAdminData['id']));
            if ($siteAdmin->checkExist()) {
                $passWord = trim($request->password);
                $passWordConFirm = trim($request->password_confirm);
                $passWordOld = trim($request->password_old);
                if (empty($passWord) || empty($passWordConFirm) || empty($passWordOld)) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
                if ($passWord != $passWordConFirm) {
                    return makeApiResponseFail(trans('shop-admin.common.password_diff'));
                }
                if (!Utils::checkPasswordStrength($passWord)) {
                    return makeApiResponseFail(trans('shop-admin.common.password_strength'));
                }
                // 验证旧密码
                if (!Hash::check($passWordOld, $siteAdmin->getModel()->password)) {
                    return makeApiResponseFail(trans('shop-admin.common.old_password_error'));
                }

                $siteAdmin->save([
                    'password' => Hash::make($passWord)
                ]);
                return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
            } else {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 管理员列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->toArray();
            $siteAdmin = new SiteAdmin();
            $data = $siteAdmin->getList($param);
            $departmentList = SiteAdminDepartment::getList(false, true);
            if ($data && $data['list']) {
                $departmentObj = $departmentList['obj'];
                foreach ($data['list'] as $item) {
                    if (!$item['role_name'] && $item['role_type'] == Constants::SiteRoleType_Admin) {
                        $item['role_name'] = '系统管理员';
                    }
                    // 匹配部门
                    $item['department_name'] = $this->getDepartmentInfo($item['department_id'], $departmentObj);
                    unset($item['password']);
                }
            }
            // 展示权限列表
            if ($request->input('show_role_list')) {
                $siteRole = new SiteRole();
                $siteRoleData = $siteRole->getList(['show_all' => true]);
                $data['role_list'] = $siteRoleData['list'];
            }

            // 是否需要获取部门列表
            if ($request->input('show_department_list')) {
                $data['department_list'] = $departmentList['list'];
            }
            $shopConfig = (new ShopConfig())->getInfo();
            $staff_num = $shopConfig['info']['staff_num'];
            $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
            $curLicense = $sn->getCurLicense();
            if ($staff_num == 0) {
                switch (true) {
                    case $curLicense == LibsConstants::License_STANDARD:
                        $staff_num = 3;
                        break;
                    case $curLicense == LibsConstants::License_DISTRIBUTION:
                        $staff_num = 10;
                        break;
                    case $curLicense == LibsConstants::License_AGENT_DISTRIBUTION:
                        $staff_num = 20;
                        break;
                    case $curLicense == LibsConstants::License_GROUP:
                        $staff_num = 20;
                        break;
                    case $curLicense == LibsConstants::License_MICRO_CLOUDSTOCK:
                        $staff_num = 20;
                        break;
                }
            }
            $data['show_add'] = ($staff_num + 1) <= $data['total'] ? false : true;
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 员工导出
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportStaffList(Request $request)
    {
        try {
            $param = $request->toArray();
            $siteAdmin = new SiteAdmin();
            $data = $siteAdmin->getList($param);
            $departmentList = SiteAdminDepartment::getList(false, true);
            if ($data && $data['list']) {
                $departmentObj = $departmentList['obj'];
                foreach ($data['list'] as $item) {
                    if (!$item['role_name'] && $item['role_type'] == Constants::SiteRoleType_Admin) {
                        $item['role_name'] = '系统管理员';
                    }
                    // 匹配部门
                    $item['department_name'] = $this->getDepartmentInfo($item['department_id'], $departmentObj);
                    unset($item['password']);
                }
            }
            $exportData = [];
            $exportFileName = 'YuanGong-' . date("YmdHis");
            if ($data && $data['list']) {
                foreach ($data['list'] as $item) {
                    $exportData[] = [
                        $item->name,
                        $item->mobile,
                        $item->username,
                        $item->position,
                        implode('-', $item->department_name),
                        $item->member_count ? $item->member_count : "0",
                        $item->distributor_count ? $item->distributor_count : "0",
                        $item->agent_count ? $item->agent_count : "0",
                        $item->dealer_count ? $item->dealer_count : "0",
                        $item->role_name,
                        $item->status ? "启用" : "禁用"
                    ];
                }
            }
            // 表头
            $exportHeadings = [
                '员工姓名',
                '手机号码',
                '帐号',
                '职位',
                '部门',
                '发展会员',
                '发展分销商',
                '发展代理商',
                '发展经销商',
                '角色权限',
                '状态'
            ];
            // 导出
            $exportObj = new Export(new Collection($exportData), $exportFileName . '.xlsx', $exportHeadings);
            return $exportObj->export();
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取部门名称
     * @param int $departmentId 部门id
     * @param array $departmentObj 所有部门信息 对象
     * @return array
     */
    private function getDepartmentInfo($departmentId, $departmentObj)
    {
        if ($departmentId && $department = $departmentObj[$departmentId]) {
            if ($department['parent_id'] > 0 && $parentDepartment = $departmentObj[$department['parent_id']]) {
                return [$parentDepartment['name'], $department['name']];
            } else {
                return [$department['name']];
            }
        }
        return [];
    }

    /**
     * 管理员详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = intval($request->id);
            $adminData = [];
            if ($id) {
                $siteAdmin = new SiteAdmin($id);
                if (!$siteAdmin->checkExist()) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                } else {
                    $adminData = $siteAdmin->getModel()->toArray();
                }
            }

            $siteRole = new SiteRole();
            $siteRoleData = $siteRole->getList(['show_all' => true]);
            $departmentData = SiteAdminDepartment::getList();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                'admin' => $adminData,
                'role_list' => $siteRoleData['list'],
                'department_list' => $departmentData
            ]);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $curSiteAdmin = SiteAdmin::getLoginedAdmin();

            $userName = trim($request->input('username', ''));
            $mobile = trim($request->input('mobile', ''));
            $passWord = trim($request->input('password', ''));
            $name = trim($request->input('name', ''));
            $status = $request->input('status') ? 1 : 0;
            $roleId = intval($request->input('role_id'));
            $id = intval($request->input('id'));
            $position = trim($request->input('position', ''));
            $headurl = trim($request->input('headurl', null));
            $departmentId = trim($request->input('department_id', ''));

            if (empty($userName) || empty($name) || empty($mobile)) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }

            $needCheckUserName = true;
            $needCheckMobile = true;
            $siteAdmin = null;
            if ($id) {
                $siteAdmin = new SiteAdmin($id);
                if (!$siteAdmin->checkExist()) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
                if (strtolower($siteAdmin->getModel()->username) == strtolower($userName)) {
                    $needCheckUserName = false;
                }
                if (strtolower($siteAdmin->getModel()->mobile) == $mobile) {
                    $needCheckMobile = false;
                }

                // 超级管理员只能由超级管理员自身修改
                if ($siteAdmin->isSystemAdmin() && $siteAdmin->getModel()->id != $curSiteAdmin['id']) {
                    return makeApiResponseFail(trans('不能编辑超级管理员'));
                }
            } else {
                if (empty($passWord)) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
            }
            // 检查用户名是否存在
            if ($needCheckUserName && SiteAdmin::userNameExist($userName)) {
                return makeApiResponseFail(trans('shop-admin.admin.user_exist'));
            }
            // 检查手机号是否存在
            if ($needCheckMobile && SiteAdmin::mobileExist($mobile)) {
                return makeApiResponseFail('手机号已存在');
            }

            $param = [
                'username' => $userName,
                'name' => $name,
                'status' => $status,
                'role_id' => $roleId,
                'mobile' => $mobile,
                'position' => $position,
                'headurl' => $headurl,
                'department_id' => $departmentId,
            ];
            if (!empty($passWord)) {
                if (!Utils::checkPasswordStrength($passWord)) {
                    return makeApiResponseFail(trans('shop-admin.common.password_strength'));
                }
                $param['password'] = Hash::make($passWord);
            }
            if (is_null($siteAdmin)) {
                $param['site_id'] = Site::getCurrentSite()->getSiteId();
                $siteAdmin = new SiteAdmin();
            } else if ($siteAdmin->isSystemAdmin()) {
                // 超级管理员不能修改用户名和角色权限
                unset($param['username']);
                unset($param['role_id']);
            }

            $id = $siteAdmin->save($param);
            if ($id) {
                return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), ['id' => $id]);
            } else {
                return makeApiResponseSuccess(trans('shop-admin.common.action_fail'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 变更状态
     * @param Request $request
     * @return array
     */
    public function status(Request $request)
    {
        try {
            $id = intval($request->input('id'));
            $status = $request->input('status') ? 1 : 0;
            if ($id) {
                $siteAdmin = new SiteAdmin($id);
                if (!$siteAdmin->checkExist()) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                } else {
                    // 超级管理员不能变更状态
                    if ($siteAdmin->isSystemAdmin()) {
                        return makeApiResponseFail(trans('shop-admin.common.data_error'));
                    }
                    $siteAdmin->save(['status' => $status]);
                }
            } else {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
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
            $headUrl = SiteAdmin::uploadHeadImage($request->file('head_image'));
            return makeApiResponseSuccess('ok', ['headurl' => $headUrl]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除员工
     * @param Request $request
     * @return array
     */
    public function deleteAdmin(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            if (!$id) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $deleteType = $request->input('delete_type', 0);
            $otherAdminId = $request->input('other_admin_id', 0);
            SiteAdmin::delete($id, $deleteType, $otherAdminId);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取员工发展的会员数量
     * @param Request $request
     * @return array
     */
    public function getMemberCount(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            if (!$id) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $count = SiteAdmin::getMemberCount($id);
            return makeApiResponseSuccess('ok', ['count' => $count]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 检测用户名是否重复
     * @param Request $request
     * @return array
     */
    public function checkUserName(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            $userName = $request->input('username', '');
            if (!$userName) {
                return makeApiResponse(400, '登录账号不能为空');
            }
            $check = true;
            if (!$id) {
                if (SiteAdmin::userNameExist($userName)) {
                    $check = false;
                }
            } else {
                $siteAdmin = new SiteAdmin($id);
                if (strtolower($siteAdmin->getModel()->username) != strtolower($userName) && SiteAdmin::userNameExist($userName)) {
                    $check = false;
                }
            }
            if (!$check) {
                return makeApiResponse(401, '登录账号已存在，请重新输入');
            } else {
                return makeApiResponseSuccess('ok');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 检测手机号是否重复
     * @param Request $request
     * @return array
     */
    public function checkMobile(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            $mobile = $request->input('mobile', '');
            if (!$mobile) {
                return makeApiResponse(400, '手机号不能为空');
            }
            $check = true;
            if (!$id) {
                if (SiteAdmin::mobileExist($mobile)) {
                    $check = false;
                }
            } else {
                $siteAdmin = new SiteAdmin($id);
                if (strtolower($siteAdmin->getModel()->mobile) != strtolower($mobile) && SiteAdmin::mobileExist($mobile)) {
                    $check = false;
                }
            }
            if (!$check) {
                return makeApiResponse(401, '手机号已存在，请重新输入');
            } else {
                return makeApiResponseSuccess('ok');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}