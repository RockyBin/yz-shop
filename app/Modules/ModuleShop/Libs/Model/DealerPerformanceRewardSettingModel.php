<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 经销商业绩奖励配置模型
 * Class AgentPerformanceRewardSettingModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class DealerPerformanceRewardSettingModel extends BaseModel
{
    protected $table = 'tbl_dealer_performance_reward_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'enable',
        'auto_check',
        'give_period',
        'payee'
    ];
}
