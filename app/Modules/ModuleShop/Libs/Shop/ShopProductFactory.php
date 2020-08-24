<?php
/**
 * 商品工厂类
 * User: liyaohui
 * Date: 2020/4/9
 * Time: 15:09
 */

namespace App\Modules\ModuleShop\Libs\Shop;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSkusModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Supplier\SupplierGroupBuyingShopProduct;
use App\Modules\ModuleShop\Libs\Supplier\SupplierShopProduct;

class ShopProductFactory
{
    /**
     * @param int|ProductModel $productIdOrModel
     * @param int|ProductSkusModel $skuId
     * @param int $num
     * @param int $type
     * @param array $params
     * @return GroupBuyingShopProduct|NormalShopProduct|SupplierShopProduct|SupplierGroupBuyingShopProduct
     * @throws \Exception
     */
    public static function createShopProduct($productIdOrModel, $skuId = 0, $num = 1, $type = Constants::OrderType_Normal, $params = []) {
        $productModel = null;
        if(is_numeric($productIdOrModel)){
            if($type == Constants::OrderType_GroupBuying){
                $groupProductSku = GroupBuyingSkusModel::query()
                    ->where('id', $skuId)
                    ->first();
                $productModel = ProductModel::find($groupProductSku->master_product_id);
            }
            else $productModel = ProductModel::find($productIdOrModel);
        } else {
            $productModel = $productIdOrModel;
        }
        if ($type == Constants::OrderType_Normal && !$productModel->supplier_member_id) {
            $product = new NormalShopProduct($productModel, $skuId, $num);
        } elseif ($type == Constants::OrderType_Normal && $productModel->supplier_member_id) {
            $product = new SupplierShopProduct($productModel, $skuId, $num);
        } elseif ($type == Constants::OrderType_GroupBuying && !$productModel->supplier_member_id) {
            $product = new GroupBuyingShopProduct($skuId, $num, $params);
        } elseif ($type == Constants::OrderType_GroupBuying && $productModel->supplier_member_id) {
            $product = new SupplierGroupBuyingShopProduct($skuId, $num, $params);
        }  else {
            throw new \Exception('product type error');
        }
        return $product;
    }
}