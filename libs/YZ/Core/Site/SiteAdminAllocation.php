<?php
namespace YZ\Core\Site;

use YZ\Core\Model\SiteAdminAllocationModel;
use YZ\Core\Model\SiteAdminModel;

/**
 * 员工流量分配逻辑类
 * Class SiteAdminAllocation
 * @package YZ\Core\Site
 */
class SiteAdminAllocation
{
    private $_siteId = 0;
    private $_model = null;
    public function __construct($idOrModel = 0)
    {
        $this->_siteId = getCurrentSiteId();
        if (!$idOrModel) $idOrModel = $this->_siteId;
        if (is_numeric($idOrModel) && $idOrModel > 0) $this->_model = SiteAdminAllocationModel::find($idOrModel);
        else if ($idOrModel instanceof SiteAdminAllocationModel) $this->_model = $idOrModel;
        if (!$this->_model) {
            $this->_model = new SiteAdminAllocationModel();
            $this->_model->site_id = $this->_siteId;
            $this->_model->status = 0;
            $this->_model->type = 1;
            $this->_model->people_type = 1;
            $this->_model->admins = "";
        }
    }

    /**
     * 保存设置数据
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public function save($params)
    {
        $params['site_id'] = $this->_siteId;
        //处理分配对象的数据，去除多余的数据再保存，并且保存格式是对的
        if($params['admins'] && is_array($params['admins'])){
            //去掉多余的数据再保存
            foreach ($params['admins'] as $key => $val){
                $newVal = ['id' => $val['id']];
                $params['admins'][$key] = $newVal;
            }
            $params['admins'] = json_encode($params['admins']);
        }else{
            $params['admins'] = "[]";
        }
        return $this->_model->fill($params)->save();
    }

    /**
     * 返回数据库记录模型
     * @return null|SiteAdminAllocationModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 获取设置信息
     * @return array
     */
    public function getInfo()
    {
        if($this->_model->admins) {
            $admins = json_decode($this->_model->admins,true);
            $ids = [];
            foreach ($admins as $item) {
                $ids[] = $item['id'];
            }
            if(count($ids)){
                $list = SiteAdminModel::query()->where(['site_id' => $this->_model->site_id])->whereIn('id',$ids)->orderByRaw('find_in_set(id,"'.implode(',',$ids).'")')->get();
            }
            $this->_model->admins = $list;
        }
        if(!$this->_model->admins) $this->_model->admins = [];
        return $this->_model;
    }

    /**
     * 按规则进行自动分配并返回员工ID
     * @return int
     */
    public function allocate(){
        $adminId = 0;
        if($this->_model && $this->_model->status){
            if ($this->_model->people_type == 1){
                $list = SiteAdminModel::query()->where(['site_id' => $this->_model->site_id,'status' => 1])->get();
            }else{
                $admins = json_decode($this->_model->admins,true);
                $ids = [];
                foreach ($admins as $item) {
                    $ids[] = $item['id'];
                }
                if(count($ids)){
                    $list = SiteAdminModel::query()->where(['site_id' => $this->_model->site_id,'status' => 1])->whereIn('id',$ids)->get();
                }
            }
            if($list){
                $index = randInt(0,count($list));
                $adminId = $list[$index]->id;
            }
        }
        return $adminId;
    }
}