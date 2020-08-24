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
class AgentOtherRewardSettingModel extends BaseModel
{
    protected $table = 'tbl_agent_other_reward_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'status',
        'commision',
        'people_num',
        'type'
    ];
}
