<?php

namespace App\Modules\ModuleShop\Libs\Supplier;

use App\Modules\ModuleShop\Libs\Shop\GroupBuyingShopOrder;

class SupplierGroupBuyingShopOrder extends GroupBuyingShopOrder
{
    use SupplierShopOrderTrait;

    /**
     * 供应商拼团订单
     * @param int $memberId
     * @param array $params
     */
    public function __construct($memberId = 0, $params = [])
    {
        parent::__construct($memberId, $params);
    }

    /**
     * @param null $isSuccess
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function groupBuyingSuccessAfterUpdate($isSuccess = null)
    {
        parent::groupBuyingSuccessAfterUpdate($isSuccess);
        $this->createSettleData();
    }
}