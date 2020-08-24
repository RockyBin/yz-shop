<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

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
     * @param string $productId 升级所需的产品ID
     */
    public static function createInstance($conditionType, $conditionValue, $productId): ICondition
    {
        $iType = intval($conditionType);
        $instance = new UpgradeConditionUnknow($conditionValue);
        switch ($iType) {
            case Constants::AgentLevelUpgradeCondition_SelfAgentLevel:
                $instance = new UpgradeConditionSelfAgentLevel($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_SelfDistributionLevel:
                $instance = new UpgradeConditionSelfDistributionLevel($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_AgentTeamMember:
                $instance = new UpgradeConditionAgentTeamMember($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_RecommendThreeLevelAgentNum:
                $instance = new UpgradeConditionRecommendThreeLevelAgentNum($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_DirectlyDistributionMember:
                $instance = new UpgradeConditionDirectlyDistributionMember($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_IndirectDistributionMember:
                $instance = new UpgradeConditionIndirectDistributionMember($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_DirectlyMember:
                $instance = new UpgradeConditionDirectlyMember($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_IndirectMember:
                $instance = new UpgradeConditionIndirectMember($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_TeamArbitrarilyLevelDistributionMember:
                $instance = new UpgradeConditionTeamArbitrarilyLevelDistributionMember($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_SelfOrderMoney:
                $instance = new UpgradeConditionSelfOrderMoney($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_DirectlyOrderMoney:
                $instance = new UpgradeConditionDirectlyOrderMoney($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_IndirectOrderMoney:
                $instance = new UpgradeConditionIndirectOrderMoney($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_TeamOrderMoney:
                $instance = new UpgradeConditionTeamOrderMoney($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_SelfBuyDesignatedProduct:
                $instance = new UpgradeConditionSelfBuyDesignatedProduct($conditionValue, $productId);
                break;
            case Constants::AgentLevelUpgradeCondition_DirectlyBuyDesignatedProduct:
                $instance = new UpgradeConditionDirectlyBuyDesignatedProduct($conditionValue, $productId);
                break;
            case Constants::AgentLevelUpgradeCondition_IndirectBuyDesignatedProduct:
                $instance = new UpgradeConditionIndirectBuyDesignatedProduct($conditionValue, $productId);
                break;
            case Constants::AgentLevelUpgradeCondition_TeamBuyDesignatedProduct:
                $instance = new UpgradeConditionTeamBuyDesignatedProduct($conditionValue, $productId);
                break;
            case Constants::AgentLevelUpgradeCondition_RecommendOneLevelAgentNum:
                $instance = new UpgradeConditionRecommendOneLevelAgentNum($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_RecommendTwoLevelAgentNum:
                $instance = new UpgradeConditionRecommendTwoLevelAgentNum($conditionValue);
                break;
            case Constants::AgentLevelUpgradeCondition_SelfBuyAllDesignatedProduct:
                $instance = new UpgradeConditionSelfBuyAllDesignatedProduct($conditionValue, $productId);
                break;
            case Constants::AgentLevelUpgradeCondition_TotalChargeMoney:
                $instance = new UpgradeConditionTotalChargeMoney($conditionValue, $productId);
                break;
            case Constants::AgentLevelUpgradeCondition_OnceChargeMoney:
                $instance = new UpgradeConditionOnceChargeMoney($conditionValue, $productId);
                break;
            default:
                break;
        }
        return $instance;
    }
}
