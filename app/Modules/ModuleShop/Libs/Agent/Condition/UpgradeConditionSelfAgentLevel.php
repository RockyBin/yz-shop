<?php

namespace App\Modules\ModuleShop\Libs\Agent\Condition;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use  App\Modules\ModuleShop\Libs\Member\Member;

/**
 * 自身代理等级的分销商等级升级条件
 */
class UpgradeConditionSelfAgentLevel extends abstractCondition
{
    protected $value = '';
    protected $name = "自身代理等级";

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getTitle()
    {
        $levelText = Constants::getAgentLevelTextForAdmin($this->value);
        return '自身代理等级为 ' .$levelText  ;
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
        $Member = new Member($memberId);
        $memberAgentLevel = $Member->getModel()->agent_level;
        if ($this->value) {
            //因为等级有可能有多个;
            $value = explode(',', $this->value);
            foreach ($value as $item) {
                if ($item && $memberAgentLevel != 0 && $memberAgentLevel == $item) {
                    return true;
                    break;
                }
            }
        }
        return false;
    }
}
