<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 团队业绩奖励配置模型
 * Class AgentPerformanceRewardSettingModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class AgentPerformanceRewardSettingModel extends BaseModel
{
    protected $table = 'tbl_agent_performance_reward_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'enable',
        'auto_check',
        'count_period',
        'give_period',
        'give_agent_level',
    ];
}
