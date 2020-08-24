<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\CloudStock\ShopCart;
use App\Modules\ModuleShop\Libs\CloudStock\ShopProduct;
use App\Modules\ModuleShop\Libs\Model\CloudStockShopCartModel;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Http\Request;
use YZ\Core\Payment\Payment;

class ShopCartController extends BaseMemberController
{
    public function getList(Request $request)
    {
        try {
            $cart = new ShopCart($this->memberId);
            $page = $request->get('page', 1);
            $pageSize = $request->get('page_size', 15);
            $data = $cart->getProductList($page, $pageSize, 1);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function removeSku(Request $request)
    {
        try {
            $data = $request->get('data');
            $cart = new ShopCart($this->memberId);
            $cart->removeSku($data);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function setSkuNum(Request $request)
    {
        try {
            $productId = $request->get('product_id');
            $skuId = $request->get('sku_id');
            $num = $request->get('num');
            $cart = new ShopCart($this->memberId);
            $data = [['product_id' => $productId, 'sku_id' => $skuId, 'num' => $num]];
            $res = $cart->setSkus($data);
            return makeApiResponseSuccess('ok', $res['data'][0]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function setSkuNumBatch(Request $request)
    {
        try {
            $data = $request->get('data');
            $cart = new ShopCart($this->memberId);
            $res = $cart->setSkus($data);
            return makeApiResponseSuccess('ok', $res['data']);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getProductMoney(Request $request)
    {
        try {
            $data = $request->get('data');
            $cart = new ShopCart($this->memberId);
            $money = $cart->calCartMoney($data);
            return makeApiResponseSuccess('ok', $money);
        } catch (\Exception $e) {
            return makeApiResponseError($e);

        }
    }

    /**
     * 一键补货
     * @param Request $request
     * @return array
     */
    public function onceReplenish(Request $request)
    {
        try {
            $orderId = $request->order_id;
            if (!$orderId) {
                makeApiResponseFail('请传入正确的订单ID');
            }
            $cart = new ShopCart($this->memberId);
            // 购物车产品数量
             $cart->onceReplenish($orderId);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }

    }
}