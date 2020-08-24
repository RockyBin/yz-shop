<?php
namespace App\Modules\ModuleShop\Libs\Supplier;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;

class SupplierShopOrder extends BaseShopOrder
{
    use SupplierShopOrderTrait;

    /**
     * 供应商普通订单
     * @param int $memberId 会员ID
     * @param array $params 额外参数
     */
    public function __construct($memberId = 0, $params = [])
    {
        parent::__construct($memberId);
        $this->setOrderType(Constants::OrderType_Normal);
    }

    public function payAfter($payInfo){
        parent::payAfter($payInfo);
        $this->createSettleData();
    }
}