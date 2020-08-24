<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 团队其他奖励配置模型
 * Class AgentOtherRewardSettingModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class AgentOtherRewardModel extends BaseModel
{
    protected $table = 'tbl_agent_other_reward';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'type',
        'reward_member_id',
        'pay_reward_member_id',
        'order_id',
        'status',
        'reward_money',
        'created_at',
        'success_at',
    ];
}
