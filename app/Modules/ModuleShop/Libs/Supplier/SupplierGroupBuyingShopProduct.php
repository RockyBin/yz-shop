<?php
namespace App\Modules\ModuleShop\Libs\Supplier;

use App\Modules\ModuleShop\Libs\Shop\GroupBuyingShopProduct;

class SupplierGroupBuyingShopProduct extends GroupBuyingShopProduct
{
	use SupplierShopProductTrait;

	private $_supplierBaseSetting = null;

    /**
     * GroupBuyingShopProduct constructor.
     * @param int $num
     * @param $groupSkuId
     * @param $params
     * @throws \Exception
     */
    public function __construct($groupSkuId, $num = 1, $params = [])
    {
        parent::__construct($groupSkuId, $num, $params);
    }
}