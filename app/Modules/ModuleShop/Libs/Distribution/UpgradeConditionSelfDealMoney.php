<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Member\Member;

/**
 * 自购成交金额类型的分销商等级升级条件
 */
class UpgradeConditionSelfDealMoney extends abstractCondition
{
    protected $name = "自购订单金额";

    public function __construct($value)
    {
        $this->value = $value * 100;
    }

    /**
     * 获取此升级条件的说明文本
     * @return string
     */
    public function getDesc()
    {
        return $this->getTypeName() . "满 " . moneyCent2Yuan($this->value) . " 元";
    }

    /**
     * 判断某分销商是否满足此分销条件
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->beforeCheckUpgrade($params)) {
            return false;
        }
        $member = new Member($memberId);
        $memberModel = $member->getModel();
        $DistributionSetting = (new DistributionSetting())->getInfo();
        $calc_valid_condition = $DistributionSetting['baseinfo']['calc_upgrade_valid_condition'];
        $money = $calc_valid_condition == 1 ? $memberModel->deal_money : $memberModel->buy_money;
        // $memberModel->
        return $money >= $this->value;
    }
}
