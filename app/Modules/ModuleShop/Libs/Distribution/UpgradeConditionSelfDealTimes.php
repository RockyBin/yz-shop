<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Member\Member;

/**
 * 自购成交次数类型的分销商等级升级条件
 */
class UpgradeConditionSelfDealTimes extends abstractCondition
{
    protected $name = "自购订单笔数";

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
        return $this->getTypeName() . "满 " . $this->value . " 笔";
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
        $DistributionSetting = (new DistributionSetting())->getBaseInfo();
        $calc_valid_condition = $DistributionSetting['calc_upgrade_valid_condition'];
        $time = $calc_valid_condition == 1 ? $memberModel->deal_times : $memberModel->buy_times;
        return $time >= $this->value;
    }
}
