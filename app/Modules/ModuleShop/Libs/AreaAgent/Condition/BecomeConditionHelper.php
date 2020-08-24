<?php

namespace App\Modules\ModuleShop\Libs\AreaAgent\Condition;

use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentConstants;
/**
 * 成为条件的静态工具类
 *
 * @author Administrator
 */
class BecomeConditionHelper
{

    /**
     * 根据成为条件的类型和值生成相应对象
     * @param int $conditionType 条件类型
     * @param int $conditionValue 条件值
     * @param string $productId 升级所需的产品ID
     */
    public static function createInstance($conditionType, $conditionValue): ICondition
    {
        $instance = new BecomeConditionUnknow($conditionValue);
        switch ($conditionType) {
            case AreaAgentConstants::AreaAgentApplySelfLevel_Agent:
                $instance = new BecomeConditionSelfAgentLevel($conditionValue);
                break;
            case AreaAgentConstants::AreaAgentApplySelfLevel_Distribution:
                $instance = new BecomeConditionSelfDistributionLevel($conditionValue);
                break;
            default:
                break;
        }
        return $instance;
    }
}
