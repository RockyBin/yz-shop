<?php
/**
 * 经销商奖金助手类
 * User: liyaohui
 * Date: 2020/1/6
 * Time: 15:12
 */

namespace App\Modules\ModuleShop\Libs\Dealer;


use App\Modules\ModuleShop\Libs\Constants;

class DealerRewardHelper
{
    /**
     * 创建对应的经销商奖金
     * @param $id
     * @param $type
     * @return IDealerReward
     */
    public static function createInstance($id, $type) :IDealerReward
    {
        $id = intval($id);
        $type = intval($type);
        switch ($type) {
            case Constants::DealerRewardType_Performance:
                return new DealerPerformanceReward($id);
                break;
            case Constants::DealerRewardType_Recommend:
                return new DealerRecommendReward($id);
                break;
            case Constants::DealerRewardType_Sale:
                return new DealerSaleReward($id);
                break;
            case Constants::DealerRewardType_Order:
                return new DealerOrderReward($id);
                break;
        }
    }
}