<?php
/**
 * 提货购物车
 * User: liyaohui
 * Date: 2019/9/4
 * Time: 13:55
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;


use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\CloudStock\TakeDeliveryShoppingCart;
use Illuminate\Http\Request;
use YZ\Core\Member\Auth;

class TakeDeliveryShoppingCartController extends BaseMemberController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 添加商品到购物车
     * @param Request $request
     * @return array|bool
     */
    public function addToCart(Request $request){
        try {
            $items = $request->input('items', []);
            $shoppingCart = new TakeDeliveryShoppingCart($this->memberId);
            $add = $shoppingCart->addToCart($items);
            return $add;
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取购物车中商品列表
     * @param Request $request
     * @return array
     */
    public function getProductList(Request $request){
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 15);
            $shoppingCart = new TakeDeliveryShoppingCart($this->memberId);
            $params['inventory'] = $request->input('inventory', 1);
            $list = $shoppingCart->getShoppingCartProductList($params, $page, $pageSize);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 购物车商品数量减少
     * @param Request $request
     * @return array
     */
    public function ShoppingCartDecrementProduct(Request $request){
        try {
            $id = $request->input('id', 0);
            $decrementNum = $request->input('decrement_num', 1);
            $shoppingCart = new TakeDeliveryShoppingCart($this->memberId);
            $update = $shoppingCart->decrement($id, $decrementNum);
            if (is_numeric($update)) {
                return makeApiResponseSuccess('ok');
            } else {
                return $update;
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 购物车商品数量增加
     * @param Request $request
     * @return array|int
     */
    public function ShoppingCartIncrementProduct(Request $request){
        try {
            $id = $request->input('id', 0);
            $incrementNum = $request->input('increment_num', 1);
            $shoppingCart = new TakeDeliveryShoppingCart($this->memberId);
            $update = $shoppingCart->increment($id, $incrementNum);
            if (is_numeric($update)) {
                return makeApiResponseSuccess('ok');
            } else {
                return $update;
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除购物车商品
     * @param Request $request
     * @return array
     */
    public function ShoppingCartRemoveProduct(Request $request){
        try {
            $ids = $request->input('ids', []);
            $shoppingCart = new TakeDeliveryShoppingCart($this->memberId);
            $remove = $shoppingCart->remove($ids);
            if ($remove !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, trans('shop-front.shop.save_fail'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}