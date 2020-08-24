<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;


use YZ\Core\Constants;
use YZ\Core\Model\FinanceModel;

/**
 * 自购订单金额等级升级条件
 */
class UpgradeConditionTotalChargeMoney extends abstractCondition
{
    protected $value = '';
    protected $name = "累计充满金额满";
    public $unit = '元';

    public function __construct($value)
    {
        $this->value = $value;
    }



    /**
     * 判断是否满足此代理升级条件
     * @param int $memberId
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $sum = FinanceModel::query()
            ->where('member_id', $memberId)
            ->whereIn('in_type', [Constants::FinanceInType_Recharge, Constants::FinanceInType_Manual, Constants::FinanceInType_Give])
            ->where('status', 1)
            ->sum('money');
        return $sum >=  $this->value;
    }
}
