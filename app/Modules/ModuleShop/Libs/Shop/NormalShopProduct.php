<?php
namespace App\Modules\ModuleShop\Libs\Shop;


/**
 * 普通商品
 */
class NormalShopProduct extends BaseShopProduct {

    public function __construct($productId, $skuId = 0, $num = 1)
    {
        parent::__construct($productId,$skuId,$num);
    }
}
