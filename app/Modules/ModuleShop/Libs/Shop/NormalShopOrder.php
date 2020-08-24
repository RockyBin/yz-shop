<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/15
 * Time: 18:05
 */

namespace App\Modules\ModuleShop\Libs\Shop;

/**
 * 普通订单类
 * Class NormalShopOrder
 * @package App\Modules\ModuleShop\Libs\Shop
 */
class NormalShopOrder extends BaseShopOrder
{
    public function __construct($memberId = 0)
    {
        parent::__construct($memberId);
    }
}