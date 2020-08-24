<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use  YZ\Core\Model\MemberParentsModel;

/**
 * 直推成员人数等级升级条件
 */
class UpgradeConditionDirectlyMember extends abstractCondition
{
    protected $value = '';
    protected $name = "直推成员人数满";

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
        $count = MemberParentsModel::query()
            ->where('parent_id', '=', $memberId)
            ->where('level', '=', '1')
            ->count();
        return $count >= $this->value;
    }
}
