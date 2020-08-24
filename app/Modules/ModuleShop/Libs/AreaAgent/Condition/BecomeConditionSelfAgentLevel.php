<?php

namespace App\Modules\ModuleShop\Libs\AreaAgent\Condition;

use App\Modules\ModuleShop\Libs\Agent\AgentApplySetting;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use  App\Modules\ModuleShop\Libs\Member\Member;

/**
 * 自身代理等级条件
 */
class BecomeConditionSelfAgentLevel extends abstractCondition
{
    protected $value = '';
    protected $name = "自身代理等级";

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getTitle()
    {
        $value = myToArray($this->value);
        $levelText = '';
        foreach ($value as $level) {
            $levelText .= ' ' . Constants::getAgentLevelTextForFront($level) . ',';
        }
        return '自身代理等级为' . substr($levelText, 0, strlen($levelText) - 1);
    }

    /**
     * 判断是否满足此条件
     * @param int $memberId
     * @param array $params 额外的参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $Member = new Member($memberId);
        $memberAgentLevel = $Member->getModel()->agent_level;
        // 等级关掉，要判断一下，关了的话，为false
        $agentBaseSetting = (new AgentBaseSetting())->getSettingModel();
        if ($memberAgentLevel > $agentBaseSetting->level) return false;
        if ($this->value) {
            //因为等级有可能有多个;
            $value = myToArray($this->value);
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
