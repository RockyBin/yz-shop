<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 分销设置数据库模型
 */
class DistributionSettingModel extends BaseModel {

    protected $table = 'tbl_distribution_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'level',
        'internal_purchase',
        'review_type',
        'apply_status',
        'bind_type',
        'show_code',
        'condition',
        'buy_times',
        'buy_money',
        'calc_valid_condition',
        'calc_performance_valid_condition',
        'calc_commission_valid_condition',
        'calc_upgrade_valid_condition',
        'calc_apply_valid_condition',
        'directly_member',
        'buy_product',
        'apply_product_type',
        'apply_product'
    ];
}
