<?php

namespace App\Modules\ModuleShop\Libs\Model\Supplier;

/**
 * 提现设置模块
 * Class WithdrawConfigModel
 * @package App\Modules\Model
 */
class SupplierBaseSettingModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_supplier_base_setting';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'settlement_period',
        'cost_price_percent',
        'sale_price_percent',
        'open_member_price',
        'open_verify_again',
        'open_point',
        'open_coupon',
        'open_distribution',
        'open_agent',
        'open_area_agent'
    ];

}