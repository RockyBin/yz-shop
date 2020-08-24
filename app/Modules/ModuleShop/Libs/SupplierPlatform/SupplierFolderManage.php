<?php
/**
 * 供应商平台文件夹管理
 * User: liyaohui
 * Date: 2020/7/22
 * Time: 10:13
 */

namespace App\Modules\ModuleShop\Libs\SupplierPlatform;


use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierFileModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierFolderModel;

class SupplierFolderManage
{
    private $siteId = 0;
    private $maxNum = 100;
    private $supplierId = 0;

    /**
     * SupplierFolderManage constructor.
     * @param int $siteId
     * @throws \Exception
     */
    public function __construct($siteId = 0)
    {
        if(!$siteId) $siteId = getCurrentSiteId();
        $this->siteId = $siteId;
        $this->supplierId = SupplierPlatformAdmin::getLoginedSupplierPlatformAdminMemberId();
        if (!$this->supplierId) {
            throw new \Exception('请先登录');
        }
    }

    /**
     * 添加文件夹
     * @param $name 文件夹名称
     * @throws \Exception
     */
    public function add($name){
        $check = SupplierFolderModel::where([
            'site_id' => $this->siteId,
            'name' => $name,
            'supplier_id' => $this->supplierId
        ])->count('id');
        if($check){
            throw new \Exception('同名文件夹已经存在');
        }
        // 是否超过最大数量
        if (SupplierFolderModel::query()
                ->where('site_id', $this->siteId)
                ->where('supplier_id', $this->supplierId)
                ->count() >= $this->maxNum) {
            throw new \Exception('最多只能添加' . $this->maxNum . '个文件夹');
        }
        $folder = new SupplierFolderModel();
        $folder->site_id = $this->siteId;
        $folder->name = $name;
        $folder->supplier_id = $this->supplierId;
        $folder->save();
    }

    /**
     * 文件夹改名
     * @param $id 文件夹ID
     * @param $name 新文件夹名称
     * @throws \Exception
     */
    public function rename($id, $name){
        $check = SupplierFolderModel::query()
            ->where([
                'site_id' => $this->siteId,
                'name' => $name,
                'supplier_id' => $this->supplierId
            ])
            ->where('id','<>',$id)
            ->count('id');
        if($check){
            throw new \Exception('同名文件夹已经存在');
        }
        SupplierFolderModel::query()->where([
            'site_id' => $this->siteId,
            'id' => $id,
            'supplier_id' => $this->supplierId
        ])->update(['name' => $name]);
    }

    /**
     * 删除文件夹
     * @param $id 文件夹ID
     * @throws \Exception
     */
    public function delete($id)
    {
        // 只有空文件夹可以删除 暂时没有子文件夹
        if (SupplierFileModel::query()->where(['site_id' => $this->siteId, 'folder_id' => $id, 'supplier_id' => $this->supplierId])->first()) {
            throw new \Exception('只能删除空文件夹');
        } else {
            SupplierFolderModel::query()->where(['site_id' => $this->siteId,'id' => $id, 'supplier_id' => $this->supplierId])->delete();
        }
    }

    /**
     * 获取文件夹列表
     * @param $params 查询参数
     * @param array $sortRule 排序规则
     * @return mixed
     */
    public function getList($params,$sortRule = ['id' => 'desc']){
        $query = SupplierFolderModel::query()
            ->where('site_id',$this->siteId)
            ->where('supplier_id', $this->supplierId);
        if(isset($params['parent_id']) && $params['parent_id']) $query->where('parent_id', $params['parent_id']);
        if($params['keyword']) $query->where('name','like', '%'.$params['keyword'].'%');if($params['return_total_record']){
            return $query->count('id');
        }
        if($params['return_total_record']){
            return $query->count('id');
        }
        if($params['page_size']) $query->forPage($params['page'] ? $params['page'] : 1, $params['page_size']);
        foreach ($sortRule as $key => $direction){
            if($key) $query->orderBy($key,$direction);
        }
        return $query->get();
    }
}