<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockProduct;
use App\Modules\ModuleShop\Libs\CloudStock\ShopCart;
use App\Modules\ModuleShop\Libs\Model\CloudStockShopCartModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use Illuminate\Http\Request;
use YZ\Core\Member\Auth;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use YZ\Core\Member\Member;

class ProductListController extends BaseMemberController
{
    public function getList(Request $request)
    {
        try {
            $memberId = $this->memberId;
            $param = $request->toArray();
            $param['status'] = '1';
            if ($memberId) {
                $param['member_id'] = $memberId;
            }
            $param['price_unit'] = 'cent';
            $param['show_class'] = 0;
            $param['cloud_stock_status'] = 1;
            $param['supplier_member_id'] = 0;
            $data = Product::getList($param, $param['page'], $param['page_size']);
            $memberObj = new Member($memberId);
            $cloudStock = new CloudStock($memberId, false);
            $productIds = [];
            foreach ($data['list'] as &$pro) {
                $pro['price'] = moneyCent2Yuan($pro['price']);
                $pro['cloudstock_price'] = $pro['ori_price'];
                $productIds[] = $pro['id'];
                $pro['cart_num'] = 0;
                $pro['cart_num_old'] = $pro['cart_num'];
            }
            unset($pro);
            //读取当前页商品的所有规格
            $productSkus = [];
            if (count($productIds)) {
                $skuList = ProductSkusModel::query()->whereIn('product_id', $productIds)->get();
                foreach ($skuList as $item) {
                    $item->cloudstock_price = moneyCent2Yuan($cloudStock->getProductPrice(
                        $item->price,
                        $memberObj->getModel()->dealer_level,
                        $memberObj->getModel()->dealer_hide_level,
                        $item->cloud_stock_rule
                    ));
                    $item->price = moneyCent2Yuan($item->price);
                    $item->sku_name = $item->sku_name ? json_decode($item->sku_name, true) : [];
                    if (!$productSkus[$item->product_id]) $productSkus[$item->product_id] = [];
                    $productSkus[$item->product_id][] = $item;
                }
            }
            //加载购物车里的商品数量
            if (count($productIds)) {
                $cartList = CloudStockShopCartModel::query()
                    ->whereIn('product_id', $productIds)
                    ->where('member_id', $memberId)
                    ->select('product_quantity', 'product_id', 'product_skus_id')->get();
                $skuNums = []; //按产品规格规格的统计数量，一般用在单规格商品
                $productNums = []; //按产品的统计数量，一般用在多规格商品
                foreach ($cartList as $item) {
                    $skuNums[$item->product_skus_id] = $item->product_quantity;
                    $productNums[$item->product_id] = $productNums[$item->product_id] + $item->product_quantity;
                }
                foreach ($data['list'] as &$pro) {
                    if ($productNums[$pro['id']]) {
                        $pro['cart_num'] = $productNums[$pro['id']];
                        $pro['cart_num_old'] = $pro['cart_num'];
                    }
                    $pro['skus'] = $productSkus[$pro['id']];
                    // 最低的价格
                    $minPrice = $pro['skus'][0]['cloudstock_price'];
                    foreach ($pro['skus'] as &$item) {
                        if ($skuNums[$item->id]) $item['cart_num'] = $skuNums[$item->id];
                        else $item['cart_num'] = 0;
                        $item['cart_num_old'] = $item['cart_num'];
                        if ($minPrice > $item['cloudstock_price']) {
                            $minPrice = $item['cloudstock_price'];
                        }
                    }
                    $pro['cloudstock_price'] = $minPrice;
                    unset($item);
                }
                unset($pro);
            }
            $shopCart = (new ShopCart())->getShoppingCartNum(0, 1);
            return makeApiResponseSuccess('成功', [
                'total' => intval($data['total']),
                'page_size' => intval($data['page_size']),
                'current' => intval($data['current']),
                'last_page' => intval($data['last_page']),
                'list' => $data['list'],
                'total_cart_num' => $shopCart['product_num']
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取云仓中的商品列表
     * @param Request $request
     * @return array
     */
    public function getCloudStockProductList(Request $request)
    {
        try {
            $params = $request->all(['keyword', 'class_id']);
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 15);
            $cartExist = $request->input('cart_exist', false);
            $params['show_sku_item']=$request->input('show_sku_item');
            $product = new CloudStockProduct($this->memberId);
            return makeApiResponseSuccess('ok', $product->getProductList($params, $page, $pageSize, $cartExist));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 根据商品id 获取sku列表
     * @param Request $request
     * @return array
     */
    public function getCloudStockProductSkuList(Request $request)
    {
        try {
            $productId = $request->input('product_id', 0);
            $cartExist = $request->input('cart_exist', false);
            $product = new CloudStockProduct($this->memberId);
            $skuList = $product->getProductSkuList($productId, $cartExist);
            return makeApiResponseSuccess('ok', ['list' => $skuList]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取进货单购物车中商品数量
     * @return array
     */
    public function getTakeDeliveryShoppingCartNum()
    {
        try {
            $product = new CloudStockProduct($this->memberId);
            return makeApiResponseSuccess('ok', ['count' => $product->getShoppingCartNum()]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取云仓库存统计
     * @return array
     */
    public function getProductsCount()
    {
        try {
            $count = (new CloudStockProduct($this->memberId))->getCloudStockProductsCount();
            return makeApiResponseSuccess('ok', $count);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}