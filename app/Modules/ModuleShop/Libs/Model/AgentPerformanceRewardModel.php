<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 团队业绩奖励模型
 * Class AgentPerformanceRewardModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class AgentPerformanceRewardModel extends BaseModel
{
    protected $table = 'tbl_agent_performance_reward';
    protected $fillable = [
        'site_id',
        'member_id',
        'member_agent_level',
        'reward_money',
        'performance_money',
        'status',
        'period',
        'reason',
        'created_at',
        'checked_at',
    ];
}
