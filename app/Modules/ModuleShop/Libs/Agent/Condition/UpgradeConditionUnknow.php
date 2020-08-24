<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

/**
 * 未知类型的分销商等级升级条件(目前只用于异常控制)
 */
class UpgradeConditionUnknow extends abstractCondition
{
    protected $value = '';
    protected $name = "未知分销商升级类型";

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
        return false;
    }
}
