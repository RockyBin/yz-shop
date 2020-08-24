<?php

namespace App\Modules\ModuleShop\Libs\Activities;

use App\Modules\ModuleShop\Libs\Model\Activities\FreeFreightModel;

class FreeFreight
{
    private $_siteId = 0;
    private $_model = null;

    public function __construct($siteId = 0)
    {
        if(!$siteId) $siteId = getCurrentSiteId();
        $this->_siteId = $siteId;
        $this->_model = FreeFreightModel::query()->where('site_id',$this->_siteId)->first();
    }

    /**
     * 返回原始 model
     * @return FreeFreightModel
     */
    public function getModel(){
        return $this->_model;
    }

    /**
     * 修改
     * @param array $info
     */
    public function edit(array $info){
        if(!$this->_model) {
            $this->_model = new FreeFreightModel();
            $this->_model->site_id = $this->_siteId;
        }
        $info['site_id'] = $this->_siteId;
        $this->_model->fill($info);
        $this->_model->save();
    }

    /**
     * 检测指定的商品是否支持包邮活动，目前包邮活动为任意商品都参与，所以目前只需要返回原始模型即可
     * @param array $productIds
     */
    public function getWithProducts(array $productIds){
        return $this->getModel();
    }
}
