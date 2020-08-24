<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Constants;

/**
 * 升级条件的静态工具类
 *
 * @author Administrator
 */
class UpgradeConditionHelper
{
    /**
     * 根据升级条件的类型和值生成相应对象
     * @param int $conditionType 条件类型
     * @param int $conditionValue 条件值
     * @param array $productIds 有些条件需要的商品id
     * @return IUpgradeCondition
     */
    public static function createInstance($conditionType, $conditionValue, $productIds = []): IUpgradeCondition
    {
        $iType = intval($conditionType);
        switch ($iType) {
            case Constants::DistributionLevelUpgradeCondition_TotalCommission:
                $instance = new UpgradeConditionTotalCommission($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_SelfDealTimes:
                $instance = new UpgradeConditionSelfDealTimes($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_SelfDealMoney:
                $instance = new UpgradeConditionSelfDealMoney($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_DirectlyUnderDealTimes:
                $instance = new UpgradeConditionDirectlyUnderDealTimes($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_DirectlyUnderDealMoney:
                $instance = new UpgradeConditionDirectlyUnderDealMoney($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_SubordinateDealTimes:
                $instance = new UpgradeConditionSubordinateDealTimes($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_SubordinateDealMoney:
                $instance = new UpgradeConditionSubordinateDealMoney($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_TotalTeam:
                $instance = new UpgradeConditionTotalTeam($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_DirectlyUnderDistributor:
                $instance = new UpgradeConditionDirectlyUnderDistributor($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_DirectlyUnderMember:
                $instance = new UpgradeConditionDirectlyUnderMember($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_SubordinateDistributor:
                $instance = new UpgradeConditionSubordinateDistributor($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_SubordinateMember:
                $instance = new UpgradeConditionSubordinateMember($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_TeamBuyProduct:
                $instance = new UpgradeConditionTeamBuyProduct($conditionValue, $productIds);
                break;
            case Constants::DistributionLevelUpgradeCondition_SelfBuyProduct:
                $instance = new UpgradeConditionSelfBuyProduct($conditionValue, $productIds);
                break;
            case Constants::DistributionLevelUpgradeCondition_DirectlyBuyProduct:
                $instance = new UpgradeConditionDirectlyBuyProduct($conditionValue, $productIds);
                break;
            case Constants::DistributionLevelUpgradeCondition_IndirectBuyProduct:
                $instance = new UpgradeConditionIndirectBuyProduct($conditionValue, $productIds);
                break;
            case Constants::DistributionLevelUpgradeCondition_DirectlyAllUnderMember:
                $instance = new UpgradeConditionDirectlyAllUnderMember($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_IndirectAllUnderMember:
                $instance = new UpgradeConditionIndirectAllUnderMember($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_IndirectUnderMember:
                $instance = new UpgradeConditionIndirectUnderMember($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_IndirectUnderDistributor:
                $instance = new UpgradeConditionIndirectUnderDistributor($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_IndirectDealTimes:
                $instance = new UpgradeConditionIndirectDealTimes($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_IndirectDealMoney:
                $instance = new UpgradeConditionIndirectDealMoney($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_TotalChargeMoney:
                $instance = new UpgradeConditionTotalChargeMoney($conditionValue);
                break;
            case Constants::DistributionLevelUpgradeCondition_OnceChargeMoney:
                $instance = new UpgradeConditionOnceChargeMoney($conditionValue);
                break;
            default:
                $instance = new UpgradeConditionUnknow($conditionValue);
                break;
        }
        return $instance;
    }
}
