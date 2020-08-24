<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

/**
 * 下属下级成交次数类型的分销商等级升级条件
 */
class UpgradeConditionSubordinateDealTimes extends abstractCondition
{
    protected $name = "分销团队总订单笔数";
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
        return $this->getTypeName() . "满 " . $this->value . " 笔";
    }

    /**
     * 判断某分销商是否满足此分销条件（包括分销自购）
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        if (!$this->beforeCheckUpgrade($params)) {
            return false;
        }
        $distributionSetting = (new DistributionSetting())->getInfo();
        $calc_valid_condition = $distributionSetting['baseinfo']['calc_upgrade_valid_condition'];
        $distributor = (new Distributor($memberId))->getModel();
        $time = $calc_valid_condition == 1 ? $distributor->subordinate_deal_times : $distributor->subordinate_buy_times;
        return $time >= $this->value;
    }
}
