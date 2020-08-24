<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2020/2/25
 * Time: 09:59
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Site;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use Illuminate\Http\Request;
use YZ\Core\Site\SiteAdminDepartment;

class SiteAdminDepartmentController extends BaseSiteAdminController
{
    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public function save(Request $request){
        try {
            $data = [
                'parent_id' => $request->input('parent_id', 0),
                'name' => $request->input('name', ''),
                'sort' => $request->input('sort', 0)
            ];
            $department = new SiteAdminDepartment($request->input('id', 0));
            $save = $department->save($data);
            if ($save !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponseFail('保存失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request){
        try {
            $list = SiteAdminDepartment::getList(true);
            return makeApiResponseSuccess('ok', ['list' => $list]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取部门下级和员工情况
     * @param Request $request
     * @return array
     */
    public function getSubInfo(Request $request){
        try {
            $id = $request->input('id', 0);
            $data = (new SiteAdminDepartment($id))->getSubInfo();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除部门
     * @param Request $request
     * @return array
     */
    public function delete(Request $request){
        try {
            $id = $request->input('id', 0);
            $deleteType = $request->input('delete_type', 0);
            $departmentId = $request->input('department_id', 0);
            (new SiteAdminDepartment($id))->delete($deleteType, $departmentId);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存部门的排序
     * @param Request $request
     * @return array
     */
    public function saveSort(Request $request) {
        try {
            $data = $request->input('sort_data', []);
            $save = SiteAdminDepartment::saveSort($data);
            if ($save !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponseFail('保存失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

}