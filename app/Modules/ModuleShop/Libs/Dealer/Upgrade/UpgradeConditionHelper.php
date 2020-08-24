<?php
/**
 * 经销商升级助手类
 * User: liyaohui
 * Date: 2019/11/30
 * Time: 11:51
 */

namespace App\Modules\ModuleShop\Libs\Dealer\Upgrade;


use App\Modules\ModuleShop\Libs\Constants;

class UpgradeConditionHelper
{
    /**
     * 根据升级条件的类型和值生成相应对象
     * @param int $conditionType 条件类型
     * @param int $conditionValue 条件值
     * @param array $productIds 有些条件需要的商品id
     * @return ICondition
     */
    public static function createInstance($conditionType, $conditionValue, $productIds = []): ICondition
    {
        $type = intval($conditionType);
        switch ($type) {
            case Constants::DealerLevelUpgradeCondition_TeamDealerNum:
                $instance = new ConditionTeamDealerNum($conditionValue);
                break;
            case Constants::DealerLevelUpgradeCondition_DirectlyDealerNum:
                $instance = new ConditionDirectlyDealerNum($conditionValue);
                break;
            case Constants::DealerLevelUpgradeCondition_IndirectDealerNum:
                $instance = new ConditionIndirectDealerNum($conditionValue);
                break;
            case Constants::DealerLevelUpgradeCondition_SelfBuyMoney:
                $instance = new ConditionSelfBuyMoney($conditionValue);
                break;
            case Constants::DealerLevelUpgradeCondition_SelfBuyProduct:
                $instance = new ConditionSelfBuyProduct($conditionValue, $productIds);
                break;
            case Constants::DealerLevelUpgradeCondition_OneReChargeMoney:
                $instance = new ConditionOneRechargeMoney($conditionValue);
                break;
            case Constants::DealerLevelUpgradeCondition_TotalReChargeMoney:
                $instance = new ConditionTotalRechargeMoney($conditionValue);
                break;
            default:
                $instance = new ConditionUnknow($conditionValue);
                break;
        }
        return $instance;
    }
}