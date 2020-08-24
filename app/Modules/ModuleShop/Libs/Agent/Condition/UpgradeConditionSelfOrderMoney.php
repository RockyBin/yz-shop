<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use  App\Modules\ModuleShop\Libs\Member\Member;
use  App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;

/**
 * 自购订单金额等级升级条件
 */
class UpgradeConditionSelfOrderMoney extends abstractCondition
{
    protected $value = '';
    protected $name = "自购订单金额满";
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
        if (!$this->checkIsAgent($params)) {
            return false;
        }
        $agentUpgradeSetting = AgentUpgradeSetting::getCurrentSiteSetting();
        $member = (new Member($memberId))->getModel();
        //order_valid_condition 1 为维权期后 0 为付款后；
        $money = $agentUpgradeSetting->order_valid_condition == 1 ? $member->deal_money : $member->buy_money;
        return $money >= $this->value;
    }
}
