<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 经销商业绩奖励规则模型
 * Class AgentPerformanceRewardRuleModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class DealerPerformanceRewardRuleModel extends BaseModel
{
    protected $table = 'tbl_dealer_performance_reward_rule';
    protected $fillable = [
        'site_id',
        'dealer_level',
        'target',
        'reward_type',
        'reward',
        'created_at',
        'updated_at',
    ];
}
