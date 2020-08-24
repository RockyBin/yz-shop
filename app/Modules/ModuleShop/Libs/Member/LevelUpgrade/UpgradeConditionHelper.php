<?php
/**
 * 会员升级助手类
 */

namespace App\Modules\ModuleShop\Libs\Member\LevelUpgrade;

use App\Modules\ModuleShop\Libs\Constants;

class UpgradeConditionHelper
{
    /**
     * 根据升级条件的类型和值生成相应对象
     * @param int $conditionType 条件类型
     * @param int $conditionValue 条件值
	 * @param array $params 可变参数，根据不同的升级条件而不同
     * @return ICondition
     */
    public static function createInstance($conditionType, $conditionValue, $params = []): ICondition
    {
        $type = intval($conditionType);
        switch ($type) {
            case Constants::MemberLevelUpgradeCondition_TotalOrderMoney:
                $instance = new ConditionTotalOrderMoney($conditionValue);
                break;
            case Constants::MemberLevelUpgradeCondition_OneReChargeMoney:
                $instance = new ConditionOneReChargeMoney($conditionValue);
                break;
            case Constants::MemberLevelUpgradeCondition_TotalReChargeMoney:
                $instance = new ConditionTotalReChargeMoney($conditionValue);
                break;
            default:
                $instance = new ConditionUnknow($conditionValue);
                break;
        }
        return $instance;
    }
}