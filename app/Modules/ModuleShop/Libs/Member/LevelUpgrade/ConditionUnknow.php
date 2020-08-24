<?php
/**
 * 未知升级类型
 */

namespace App\Modules\ModuleShop\Libs\Member\LevelUpgrade;

class ConditionUnknow extends AbstractCondition
{
    protected $name = "未知会员升级类型";

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * 返回升级条件文案
     * @return mixed|string
     */
    public function getNameText()
    {
        return $this->name;
    }

    /**
     * 判断某经销商是否满足此条件
     * @param int $memberId 经销商会员id
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        return false;
    }
}