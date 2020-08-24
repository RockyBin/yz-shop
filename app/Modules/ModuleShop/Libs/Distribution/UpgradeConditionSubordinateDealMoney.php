<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use YZ\Core\Member\Member;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;

/**
 * 下属下级成交金额类型的分销商等级升级条件
 */
class UpgradeConditionSubordinateDealMoney extends abstractCondition
{
    protected $name = "分销团队总订单金额";
    protected $onlyDistributor = true;

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
        $money = $calc_valid_condition == 1 ? $distributor->subordinate_deal_money : $distributor->subordinate_buy_money;
        return $money >= $this->value;
    }
}
