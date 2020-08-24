<?php

namespace App\Modules\ModuleShop\Observer;
use App\Modules\ModuleShop\Libs\Model\ProductSkuValueModel;
use App\Modules\ModuleShop\Jobs\ResetProductSkusNameJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * 产品规格值名称观察者模型
 */
class ProductSkuValueObserver {
    use DispatchesJobs;

    /**
     * 当更改产品规格值名称时，将和此规格名称有关的产品规格里的冗余字段 tbl_product_skus.sku_name 进行同步更新，避免后面需要一对多的连表查询
     *
     * @param  ProductSkuValueModel $productSkuModel
     * @return void
     */
    public function updated(ProductSkuValueModel $productSkuModel)
    {
        $this->dispatch(new ResetProductSkusNameJob($productSkuModel->site_id,$productSkuModel->product_id,$productSkuModel->id));
    }
}
