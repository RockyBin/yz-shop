<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

/**
 * 未知类型的分销商等级升级条件(目前只用于异常控制)
 */
class UpgradeConditionUnknow extends abstractCondition
{
    protected $name = "未知分销商升级类型";
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
        return $this->getTypeName();
    }

    /**
     * 判断某分销商是否满足此分销条件
     * @param int $memberId 分销商id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        return false;
    }
}
