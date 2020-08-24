<?php

namespace App\Modules\ModuleShop\Libs\Statistics\MemberStatistics;

use App\Modules\ModuleShop\Libs\Constants;

class MemberStatistics
{
    private $_OrderModel;
    private $type;

    public function __construct($OrderModel, $type)
    {
        $this->_OrderModel = $OrderModel;
        $this->type = $type;
    }

    public static function Statistics($OrderModel, $AfterSaleModel, $type)
    {
        foreach ($type as $item) {
            switch ($item) {
                case Constants::Statistics_member_tradeMoney:
                    (new MemberTradeMoney($OrderModel, $AfterSaleModel, $item))->save();
                    continue;
                case Constants::Statistics_member_tradeTime:
                    (new MemberTradeTime($OrderModel, $AfterSaleModel, $item))->save();
                    continue;
            }
        }
    }

    /**
     * 累加云仓业绩
     * @param $orderId
     * @param $type 类型，是付款后数据还是订单完成后的数据
     */
    public static function addCloudStockPerformance($orderId, $type){
        (new MemberCloudStockPerformance($orderId, $type))->calc();
    }
}