<?php
/**
 * 购物车
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\ShoppingCart;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Activities\FreeFreight;
use App\Modules\ModuleShop\Libs\Shop\ShoppingCart;
use App\Modules\ModuleShop\Libs\Shop\ShopProductFactory;
use Illuminate\Http\Request;

class ShoppingCartController extends BaseMemberController
{

    /**
     * 获取购物车内产品列表
     * @param Request $request
     * @return array
     */
    public function getCartProductList(Request $request)
    {
        try {
            $shoppingCart = new ShoppingCart($this->memberId);
            $page = $request->input('page', 1);
            $pageSize = $request->input('pageSize', 15);
            $list = $shoppingCart->getShoppingCartProductList($page, $pageSize, intval($request->get('refresh')));

            //满额包邮
            $productIds = [];
            $freeFreight = (new FreeFreight())->getWithProducts([$productIds]);
            if($freeFreight) $freeFreight->money = moneyCent2Yuan($freeFreight->money);
            $list['free_freight'] = $freeFreight;

            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 刷新购物车内失效的产品
     * @return array
     */
    public function cartRefresh()
    {
        try {
            $shoppingCart = new ShoppingCart($this->memberId);
            $list = $shoppingCart->refresh();
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 添加产品到购物车
     * @param Request $request
     * @return array|mixed
     */
    public function addProductToCart(Request $request)
    {
        try {
            $productId = $request->input('productId', 0);
            $skuId = $request->input('skuId', 0);
            $num = $request->input('num', 1);
            if (!$productId || $num <= 0) {
                return makeApiResponse(400, trans('shop-front.shop.data_error'));
            }
            $shoppingCart = new ShoppingCart($this->memberId);
            $shopProduct = ShopProductFactory::createShopProduct($productId, $skuId, $num);
            return $shoppingCart->addProduct($shopProduct);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除购物车中的某些产品 productId可以为数组 或 产品id
     * @param Request $request
     * @return array
     */
    public function deleteProductFromCart(Request $request)
    {
        try {
            $productId = $request->input('productId', 0);
            if (!$productId) {
                return makeApiResponse(400, trans('shop-front.shop.data_error'));
            }
            $shoppingCart = new ShoppingCart($this->memberId);
            $del = $shoppingCart->removeProduct($productId);
            if ($del) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, trans('shop-front.shop.data_error'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 改变购物车内产品的数量
     * @param Request $request
     * @return array|int|mixed
     */
    public function changeCartProductNum(Request $request)
    {
        try {
            $productId = $request->input('productId', 0);
            $num = $request->input('num', 1);
            $type = $request->input('type', 1); // 类型 1为加 0为减
            if (!$productId || $num <= 0) {
                return makeApiResponse(500, trans('shop-front.shop.data_error'));
            }
            $shoppingCart = new ShoppingCart($this->memberId);
            if ($type == 1) {
                $change = $shoppingCart->increaseProductNum($productId, $num);
            } else {
                $change = $shoppingCart->decreaseProductNum($productId, $num);
            }
            return $change;
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 计算购物车内所选产品的总价
     * @param Request $request
     * @return array
     */
    public function calCartMoney(Request $request)
    {
        try {
            $product = $request->input('product', []);
            if (empty($product)) {
                return makeApiResponse(500, trans('shop-front.shop.data_error'));
            }
            $shoppingCart = new ShoppingCart($this->memberId);
            $money = $shoppingCart->calCartMoney($product);
            return makeApiResponseSuccess('ok', ['money' => $money]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

}