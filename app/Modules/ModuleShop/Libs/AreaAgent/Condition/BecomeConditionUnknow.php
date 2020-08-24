<?php

namespace App\Modules\ModuleShop\Libs\AreaAgent\Condition;

/**
 * 未知类型的区域代理成为条件(目前只用于异常控制)
 */
class BecomeConditionUnknow extends abstractCondition
{
    protected $value = '';
    protected $name = "未知区域代理成为类型";

    public function __construct($value)
    {
        $this->value = $value;

    }

    /**
     * 判断是否满足此区域代理成为条件
     * @param int $memberId
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        return false;
    }
}
