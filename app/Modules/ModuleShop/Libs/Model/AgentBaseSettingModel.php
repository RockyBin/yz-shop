<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 代理基本设置数据库模型
 */
class AgentBaseSettingModel extends BaseModel {

    protected $table = 'tbl_agent_base_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'level',
        'commision',
        'bonus_mode',
        'get_distribution_commision',
        'commision_grant_time',
        'commision_type',
        'need_initial_fee',
        'initial_fee',
        'internal_purchase',
        'internal_purchase_performance'
	];
}
