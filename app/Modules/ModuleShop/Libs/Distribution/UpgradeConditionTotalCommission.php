<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

/**
 * 佣金总收入类型的分销商等级升级条件
 */
class UpgradeConditionTotalCommission extends abstractCondition
{
    protected $name = "佣金总收益";
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
        $distributor = new Distributor($memberId);
        return $distributor->getTotalCommission() >= $this->value;
    }
}
