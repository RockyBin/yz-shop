<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 团队业绩模型
 * Class AgentPerformanceModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class AgentPerformanceModel extends BaseModel
{
    protected $table = 'tbl_agent_performance';
    protected $fillable = [
        'site_id',
        'member_id',
        'order_id',
        'money',
        'count_period',
        'created_at',
        'updated_at',
    ];
}
