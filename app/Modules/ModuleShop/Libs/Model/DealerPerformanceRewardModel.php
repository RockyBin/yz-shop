<?php
namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;

/**
 * 经销商业绩奖励模型
 * Class AgentPerformanceRewardModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class DealerPerformanceRewardModel extends BaseModel
{
    protected $table = 'tbl_dealer_performance_reward';
    protected $fillable = [
        'site_id',
        'member_id',
        'member_dealer_level',
        'member_dealer_hide_level',
        'reward_money',
        'performance_money',
        'total_performance_money',
        'period',
        'reward_id',
    ];

}
