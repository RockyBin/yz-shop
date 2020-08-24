<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Site;

use Illuminate\Http\Request;
use YZ\Core\Site\SiteRole;
use App\Http\Controllers\SiteAdmin\BaseSiteAdminController as BaseController;

/**
 * 权限
 * Class SiteRoleController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Site
 */
class SiteRoleController extends BaseController
{
    /**
     * 列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->toArray();
            $siteRole = new SiteRole();
            $data = $siteRole->getList($param);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 角色详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = intval($request->id);
            $siteRole = new SiteRole($id);
            if (!$siteRole->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            } else {
                return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                    'role' => $siteRole->getModel()->toArray(),
                    'perm' => $siteRole->getPermList()
                ]);
            }
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
            // 权限不能为空
            if (empty($request->perm)) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $id = intval($request->id);
            if ($id) {
                $siteRole = new SiteRole($id);
                if (!$siteRole->checkExist()) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
            } else {
                $siteRole = new SiteRole();
            }
            $param = $request->toArray();
            $siteRole->save($param);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除角色
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $siteRole = new SiteRole($id);
            if (!$siteRole->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            // 检查是否有用户已经是这个角色
            if ($siteRole->getStaffCount() > 0) {
                return makeApiResponseFail('此角色下有相关员工，不能删除');
            }

            $siteRole->delete();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 检测角色名称是否重复
     * @param Request $request
     * @return array
     */
    public function checkRoleName(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            $name = $request->input('name', '');
            if (!$name) {
                return makeApiResponse(400, '名称不能为空');
            }

            $role = new SiteRole($id);
            // 查询是否有重复的名称
            $checkName = $role->getRoleByName($name);
            if ($checkName && $checkName->id != $role->getModel()->id) {
                return makeApiResponse(401, '名称已存在，请重新输入');
            } else {
                return makeApiResponseSuccess('ok');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}
