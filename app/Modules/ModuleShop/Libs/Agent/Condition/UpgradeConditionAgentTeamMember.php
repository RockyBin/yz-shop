<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use  App\Modules\ModuleShop\Libs\Model\AgentParentsModel;

/**
 * 团队成员的等级升级条件
 */
class UpgradeConditionAgentTeamMember extends abstractCondition
{
    protected $value = '';
    protected $name = "团队成员满";
    protected $onlyAgent = true;

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
        $count = AgentParentsModel::query()->where('parent_id', '=', $memberId)->count();
        //var_dump(($count+1) , $this->value,$memberId);
        return ($count+1) >= $this->value;
    }
}
