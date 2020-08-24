<?php
namespace App\Modules\ModuleShop\Libs\Supplier;

use App\Modules\ModuleShop\Libs\Shop\NormalShopProduct;

class SupplierShopProduct extends NormalShopProduct
{
	use SupplierShopProductTrait;

	private $_supplierBaseSetting = null;

    /**
     * 供应商普通订单商品
     * @param $productId 商品ID
     * @param int $skuId Sku ID
     * @param int $num 订单数量
     * @throws \Exception
     */
    public function __construct($productId, $skuId = 0, $num = 1)
    {
        parent::__construct($productId,$skuId,$num);
    }
}