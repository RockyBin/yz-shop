<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig;

use YZ\Core\Site\Site;
use  App\Modules\ModuleShop\Libs\Model\OrderConfigModel;


/**
 * 订单设置类
 * Class OrderConfig
 * @package App\Modules\ModuleShop\Libs\OrderConfig
 */
class OrderConfig
{

    /**
     * 添加设置
     * @param array $info，设置信息，对应 OrderConfigModel 的字段信息
     */
    public function add()
    {
        $model = new OrderConfigModel();
        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->nopay_close_hour=1;
        $model->ordersend_success_day=15;
        $model->ordersend_close_day=15;
        $model->save();
    }

    /**
     * 编辑设置
     * @param array $info，设置信息，对应 OrderConfigModel 的字段信息
     */
    public function edit(array $info)
    {
        $model = new OrderConfigModel();
        $model=$model::query()->where(['site_id' => Site::getCurrentSite()->getSiteId()])->first();
        foreach ($info as $key => $val) {
            $model->$key = $val;
        }
        $model->save();
    }




    /**
     * 查找指定网站的订单设置
     */
    public function findInfo()
    {
        $data = OrderConfigModel::where(['site_id' => Site::getCurrentSite()->getSiteId()])->first();
        return $data;
    }

    public function getInfo()
    {
        $data = $this->findInfo();
        if (!$data) {
            $this->add();
        }
        return $this->findInfo();
    }
}