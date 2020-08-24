<?php
/**
 * Created by Aison.
 */

namespace YZ\Core\Site;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use YZ\Core\Constants;
use YZ\Core\Model\SiteAdminModel;
use YZ\Core\Model\SiteRoleModel;
use YZ\Core\Model\SiteRolePermModel;

class SiteRole
{
    private $_model = null;
    private $_siteId = 0;
    private $_permList = [];

    /**
     * 舒适化
     * SiteRole constructor.
     * @param $idOrModel
     * @param int $siteId
     */
    public function __construct($idOrModel = 0, $siteId = 0)
    {
        $siteId = intval($siteId);
        if ($siteId > 0) {
            $this->_siteId = $siteId;
        } else if ($siteId == 0) {
            $this->_siteId = Site::getCurrentSite()->getSiteId();
        }

        if (is_numeric($idOrModel)) {
            $this->init($this->find($idOrModel));
        } else {
            $this->init($idOrModel);
        }
    }

    /**
     * 查询数据 并 初始化
     * @param $roleId
     */
    private function find($roleId)
    {
        if ($roleId > 0) {
            $this->_model = SiteRoleModel::query()->where('site_id', $this->_siteId)->where('id', $roleId)->first();
            $this->init($this->_model);
        }
    }

    /**
     * 初始化数据
     * @param $model
     */
    private function init($model)
    {
        if ($model) {
            $this->_model = $model;
            $this->_siteId = $model->site_id;
            $this->_permList = $this->getPermsValue();
        }
    }

    /**
     * 返回模型
     * @return null
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 获取角色id
     * @return int
     */
    public function getRoleId()
    {
        if ($this->checkExist()) {
            return $this->_model->id;
        }
        return 0;
    }

    /**
     * 返回权限值列表
     * @return array
     */
    public function getPermList()
    {
        return $this->_permList;
    }

    /**
     * 列表
     * @param $param
     * @return Collection|static[]
     */
    public function getList($param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 1) $page = 1;
        if ($pageSize <= 1) $pageSize = 20;

        $query = SiteRoleModel::query()->where('site_id', $this->_siteId);
        if (trim($param['name'])) {
            $query->where('name', 'like', '%' . trim($param['name']) . '%');
        }
        if (is_numeric($param['status']) && intval($param['status']) >= 0) {
            $query->where('status', intval($param['status']));
        }

        // 总数据量
        $total = $query->count();
        if ($total > 0 && $param['show_all']) {
            // 展示全部
            $pageSize = $total;
        }

        $query->addSelect('tbl_site_role.*');
        $query->addSelect(DB::raw('(select count(1) from tbl_site_admin where role_id = tbl_site_role.id and site_id = ' . $this->_siteId . ' and tbl_site_admin.status != ' . Constants::SiteAdminStatus_Delete . ') as admin_num'));
        $query->forPage($page, $pageSize);
        $list = $query
            ->orderBy('id', 'desc')
            ->get();

        $last_page = ceil($total / $pageSize);

        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 保存
     * @param array $param
     * @throws \Exception
     */
    public function save(array $param)
    {
        // 保存角色基本信息
        if (empty($param['site_id'])) {
            $param['site_id'] = $this->_siteId;
        }
        if (is_null($this->_model)) {
            $this->_model = new SiteRoleModel();
            $this->_model->create_at = date('Y-m-d H:i:s');
        }
        // 查询是否有重复的名称
        $checkRole = $this->getRoleByName($param['name']);
        if ($checkRole && $checkRole->id != $this->_model->id) {
            throw new \Exception('名称已存在，请重新输入');
        }
        $this->_model->fill($param);
        $this->_model->save();
        // 保存角色权限，不能删除所有权限
        $perms = myToArray($param['perm']);
        if (count($perms) > 0) {
            // 先删除当前所有权限
            SiteRolePermModel::query()->where('site_id', $this->_siteId)->where('role_id', $this->getRoleId())->delete();
            $models = [];
            foreach ($perms as $permItem) {
                if (empty(trim($permItem))) continue;
                $insertModel = new SiteRolePermModel();
                $insertModel->perm = trim($permItem);
                $insertModel->site_id = $this->_siteId;
                $insertModel->role_id = $this->getRoleId();
                $insertModel->create_at = date('Y-m-d H:i:s');
                $models[] = $insertModel;
            }
            // 批量插入数据
            $this->_model->perms()->saveMany($models);
        }
    }

    /**
     * 根据name查询角色
     * @param $name
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function getRoleByName($name)
    {
        return SiteRoleModel::query()->where('site_id', $this->_siteId)
            ->where('name', $name)
            ->first();
    }

    /**
     * 数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->id) return true;
        else return false;
    }

    /**
     * 返回当前角色的权限
     * @return Collection
     */
    public function getPerms()
    {
        if ($this->checkExist()) {
            return $this->_model->perms();
        }
        return new Collection();
    }

    /**
     * 删除
     */
    public function delete()
    {
        if ($this->checkExist()) {
            $this->_model->delete();
            $this->_model = null;
        }
    }

    /**
     * 获取管理员数量
     * @return int
     */
    public function getStaffCount()
    {
        if ($this->checkExist()) {
            return SiteAdminModel::query()
                ->where('site_id', $this->_siteId)
                ->where('role_id', $this->_model->id)
                ->where('status', '<>', -1)
                ->count();
        }
        return 0;
    }

    /**
     * 返回当前角色的权限值
     * @return array
     */
    private function getPermsValue()
    {
        $permList = $this->getPerms();
        if ($permList->count() > 0) {
            return $permList->pluck('perm')->all();
        } else {
            return [];
        }
    }
}