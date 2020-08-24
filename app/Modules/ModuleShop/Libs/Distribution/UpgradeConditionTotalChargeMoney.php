<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use YZ\Core\Constants;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberParentsModel;

/**
 * 团队总人数类型的分销商等级升级条件
 */
class UpgradeConditionTotalChargeMoney extends abstractCondition
{
    protected $name = "累计充值金额";
    protected $onlyDistributor = true;

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 获取此升级条件的说明文本
     * @return string
     */
    public function getDesc()
    {
        return $this->getTypeName() .'满'. $this->value . " 元";
    }

    /**
     * 判断某分销商是否满足此分销条件（包括自己）
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $level = $this->getDistributionLevel($params);
        if (!$level) {
            return false;
        }
        $sum = FinanceModel::query()
            ->where('member_id', $memberId)
            ->whereIn('in_type', [Constants::FinanceInType_Recharge, Constants::FinanceInType_Manual, Constants::FinanceInType_Give])
            ->where('status', 1)
            ->sum('money');
        return $sum >= ($this->value * 100);
    }
}
