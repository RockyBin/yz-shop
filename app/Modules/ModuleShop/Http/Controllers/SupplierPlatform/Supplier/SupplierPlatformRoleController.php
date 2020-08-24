<?php
/**
 * Created by wenke.
 */


namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Supplier;

use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformRole;
use Illuminate\Http\Request;
use YZ\Core\Site\SiteRole;
use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController as BaseController;

/**
 * 权限
 * Class SiteRoleController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Site
 */
class SupplierPlatformRoleController extends BaseController
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
            // 所属供应商
            $param['member_id'] = $this->memberId;
            $supplierRole = new SupplierPlatformRole();
            $data = $supplierRole->getList($param);
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
            $supplierRole = new SupplierPlatformRole($id);
            if (!$supplierRole->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            } else {
                return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                    'role' => $supplierRole->getModel()->toArray(),
                    'perm' => $supplierRole->getPermList()
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
                $supplierRole = new SupplierPlatformRole($id);
                if (!$supplierRole->checkExist()) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
            } else {
                $supplierRole = new SupplierPlatformRole();
            }
            $param = $request->toArray();
            $param['member_id'] = $this->memberId;
            $supplierRole->save($param);
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
            $supplierRole = new SupplierPlatformRole($id);
            if (!$supplierRole->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            // 检查是否有用户已经是这个角色
            if ($supplierRole->getStaffCount() > 0) {
                return makeApiResponseFail('此角色下有相关员工，不能删除');
            }

            $supplierRole->delete();
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

            $supplierRole = new SupplierPlatformRole($id);
            // 查询是否有重复的名称
            $checkName = $supplierRole->getRoleByName($name, $this->memberId);
            if ($checkName && $checkName->id != $supplierRole->getModel()->id) {
                return makeApiResponse(401, '名称已存在，请重新输入');
            } else {
                return makeApiResponseSuccess('ok');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

}
