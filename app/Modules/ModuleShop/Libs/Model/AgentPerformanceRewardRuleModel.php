<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 团队业绩奖励规则模型
 * Class AgentPerformanceRewardRuleModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class AgentPerformanceRewardRuleModel extends BaseModel
{
    protected $table = 'tbl_agent_performance_reward_rule';
    protected $fillable = [
        'site_id',
        'agent_level',
        'target',
        'reward_type',
        'reward',
        'created_at',
        'updated_at',
    ];
}
