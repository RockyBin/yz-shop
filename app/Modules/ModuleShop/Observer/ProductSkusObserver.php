<?php

namespace App\Modules\ModuleShop\Observer;
use App\Modules\ModuleShop\Libs\Product\ProductSku;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;

/**
 * 产品规格值名称观察者模型
 */
class ProductSkusObserver {

    /**
     * 产品新建规格时，将产品规格值名称冗余到 tbl_product_skus.sku_name 字段里，避免后面需要一对多的连表查询
     *
     * @param  ProductSkusModel $productSkuModel
     * @return void
     */
    public function created(ProductSkusModel $model)
    {
        ProductSku::refreshSkuRedundancyData($model->id);
    }
}
