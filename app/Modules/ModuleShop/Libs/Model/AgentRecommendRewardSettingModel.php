<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 团队推荐奖配置模型
 * Class AgentRecommendRewardSettingModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class AgentRecommendRewardSettingModel extends BaseModel
{
    protected $table = 'tbl_agent_recommend_reward_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'enable',
        'auto_check',
        'commision',
    ];
}
