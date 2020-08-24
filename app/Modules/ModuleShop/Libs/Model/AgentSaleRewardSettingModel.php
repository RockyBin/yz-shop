<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 代理销售奖设置数据库模型
 */
class AgentSaleRewardSettingModel extends BaseModel
{

    protected $table = 'tbl_agent_sale_reward_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'enable',
        'commision_type',
        'amount_type',
        'commision',
        'lowcommision',
        'commision_relations',
        'commision_people_num'
    ];
}
