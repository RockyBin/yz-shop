<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 团队业绩奖励模型
 * Class AgentRecommendRewardModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class AgentRecommendRewardModel extends BaseModel
{
    protected $table = 'tbl_agent_recommend_reward';
    protected $fillable = [
        'site_id',
        'member_id',
        'member_agent_level',
        'sub_member_id',
        'sub_member_agent_level',
        'reward_money',
        'status',
        'reason',
        'created_at',
        'checked_at',
    ];
}
