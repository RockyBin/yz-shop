<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\ShoppingCart;

use YZ\Core\Member\Auth;
use App\Modules\ModuleShop\Libs\Shop\ShoppingCart;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController as BaseController;

/**
 * 此接口与 ShoppingCartController 的不同点在于此接口不强制提示用户登录，只是当用户未登录时，相应数据为空
 * Class ShoppingCartNumController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\ShoppingCart
 */
class ShoppingCartNumController extends BaseController
{
    /**
     * 返回购物车中商品的数量
     * @return int
     */
    public function getShoppingCartNum()
    {
        try {
            $cartLength = 0;
            $memberId = Auth::hasLogin();
            if ($memberId) {
                $shoppingCart = new ShoppingCart($memberId);
                $cartLength = $shoppingCart->getShoppingCartNum(true);
                return makeApiResponseSuccess('ok', ['cartLength' => $cartLength]);
            }
            return makeApiResponseSuccess('ok', ['cartLength' => $cartLength]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}