<?php
/**
 * 员工部门逻辑类
 * User: liyaohui
 * Date: 2020/2/21
 * Time: 18:36
 */

namespace YZ\Core\Site;


use YZ\Core\Model\SiteAdminDepartmentModel;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Model\SiteAdminModel;

class SiteAdminDepartment
{
    public $siteId = 0;
    private $_model = null;
    public function __construct($idOrModel = 0)
    {
        $this->siteId = getCurrentSiteId();
        if (is_numeric($idOrModel) && $idOrModel > 0) $this->_model = SiteAdminDepartmentModel::where(['site_id' => $this->siteId])->find($idOrModel);
        else if ($idOrModel instanceof SiteAdminDepartmentModel) $this->_model = $idOrModel;
        if (!$this->_model) $this->_model = new SiteAdminDepartmentModel();
    }

    /**
     * 保存部门数据
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public function save($params)
    {
        if ($params['parent_id']) {
            $parent = SiteAdminDepartmentModel::find($params['parent_id']);
            if (!$parent) {
                throw new \Exception('上级部门不存在');
            }
        } else {
            $params['parent_id'] = 0;
        }
        $params['site_id'] = $this->siteId;
        return $this->_model->fill($params)->save();
    }

    /**
     * 是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->id) {
            return true;
        }
        return false;
    }

    /**
     * 返回数据库记录模型
     * @return null|SiteAdminDepartmentModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 获取部门列表
     * @param bool $isCount 是否获取人数统计
     * @param bool $getObj  是否同时获取原始数据对象
     * @return array
     */
    public static function getList($isCount = false, $getObj = false)
    {
        $list = SiteAdminDepartmentModel::query()
//            ->from('tbl_site_admin_department as dep')
            ->where('site_id', getCurrentSiteId());
        if ($isCount) {
            $list->withCount(['siteAdmins as admin_count' => function ($query) {
                $query->where('status', '!=', Constants::SiteAdminStatus_Delete);
            }]);
        }
        $list = $list->orderByDesc('sort')
            ->get();
        $parents = [];
        if ($list->count() > 0) {
            $parents = $list->where('parent_id', 0)->values()->toArray();
            foreach ($parents as &$item) {
                $item['sub_list'] = $list->where('parent_id', $item['id'])->values()->toArray();
            }
        }
        if ($getObj) {
            return [
                'list' => $parents,
                'obj' => $list->keyBy('id')
            ];
        }
        return $parents;
    }

    /**
     * 获取当前部门的下级情况
     * @return array
     * @throws \Exception
     */
    public function getSubInfo()
    {
        if (!$this->checkExist()) {
            throw new \Exception('部门不存在');
        }
        $data = [
            'is_first' => false,    // 是否是一级部门
            'sub_count' => 0,       // 下级部门数量
            'admin_count' => 0      // 所有员工数量
        ];
        $subIds = [];
        // 如果是一级部门 要统计下级的数据
        if ($this->_model->parent_id == 0) {
            $data['is_first'] = true;
            $subIds = $this->getSubDepartmentIds();
            $subCount = count($subIds);
            $data['sub_count'] = $subCount;
        }
        // 获取当前部门 以及下级部门的所有员工人数
        $departmentIds = array_merge($subIds, [$this->_model->id]);
        $data['admin_count'] = SiteAdminModel::query()
            ->where('site_id', $this->siteId)
            ->whereIn('department_id', $departmentIds)
            ->where('status', '>', Constants::SiteAdminStatus_Delete)
            ->count();
        return $data;
    }

    /**
     * 获取当前部门的所有下级部门id
     * @return array
     */
    public function getSubDepartmentIds()
    {
        if ($this->_model->parent_id > 0) return [];
        return SiteAdminDepartmentModel::query()->where('site_id', $this->siteId)
            ->where('parent_id', $this->_model->id)
            ->pluck('id')->all();
    }

    /**
     * 删除当前部门
     * @param int $deleteType       删除方式 0 直接解绑员工 1 转移员工
     * @param int $departmentId     要转移的部门id
     * @throws \Exception
     */
    public function delete($deleteType = 0, $departmentId = 0)
    {
        try {
            DB::beginTransaction();
            // 获取包括自己在内的所有部门id
            $departmentIds = array_merge($this->getSubDepartmentIds(), [$this->_model->id]);
            $updateData = [];
            // 直接解绑所有员工
            if ($deleteType == 0) {
                $updateData = ['department_id' => 0];
            } else if ($deleteType == 1 && $departmentId > 0) {
                // 先检测一下要转移的部门是否存在
                $otherDepartment = new SiteAdminDepartment($departmentId);
                if (!$otherDepartment->checkExist()) {
                    throw new \Exception('要转移的部门不存在');
                }
                $updateData = ['department_id' => $departmentId];
            }
            if ($updateData) {
                SiteAdminModel::query()->where('site_id', $this->siteId)
                    ->whereIn('department_id', $departmentIds)
                    ->update($updateData);
            } else {
                throw new \Exception('请选择正确的删除方式');
            }
            // 删除相关部门数据
            SiteAdminDepartmentModel::query()->where('site_id', $this->siteId)
                ->whereIn('id', $departmentIds)
                ->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 保存部门排序
     * @param $data
     * @return bool|int
     * @throws \Exception
     */
    public static function saveSort($data)
    {
        if ($data) {
            $saveData = [];
            foreach ($data as $item) {
                if (
                    isset($item['id'])
                    && intval($item['id']) > 0
                    && isset($item['sort'])
                    && intval($item['sort']) >= 0
                ) {
                    $saveData[] = [
                        'id' => intval($item['id']),
                        'sort' => intval($item['sort'])
                    ];
                }
            }
            if (!$saveData) return false;
            $model = new SiteAdminDepartmentModel();
            return $model->updateBatch($saveData);
        } else {
            return false;
        }
    }
}