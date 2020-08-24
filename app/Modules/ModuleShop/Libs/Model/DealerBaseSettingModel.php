<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 代理基本设置数据库模型
 */
class DealerBaseSettingModel extends BaseModel
{

    protected $table = 'tbl_dealer_base_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'site_id',
        'status',
        'purchases_money_target',
        'initial_money_target',
        'recharge_balance_target',
        'pay_parent_type',
        'freight_id'
    ];
}
